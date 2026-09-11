# Elasticsearch indexing — Mongo change streams

**Scope:** Spring service (`service/`). Search/query code uses `ElasticsearchService`; **keeping the ES index in sync with MongoDB** is **not** done by sprinkling `save` calls across every repository method by default.

## Canonical flow

1. **`MongoConfiguration`** (`com.goveca.MongoConfiguration`) starts a **change stream watcher** per entry in **`EsSyncCollection`**, using **`getMongoCollectionName()`** (same as the enum constant name, which must match `@Document(collection = ...)`). Each watcher uses `fullDocument(UPDATE_LOOKUP)` and runs on a cached thread pool (`listenToChangeStream` `@PostConstruct`).
2. Every change event is delegated to **`MongoEventConsumer.process(ChangeStreamDocument<Document>)`** (`com.goveca.MongoEventConsumer`).
3. **`MongoEventConsumer`** resolves **`EsSyncCollection.fromMongoCollectionName(...)`**, then dispatches on **`ProcessingStrategy`** and **`isSyncToEs()`**:
   - **Deletes:** `ElasticsearchService.delete(index, id)` only when **`syncToEs == true`**. ID comes from **`extractIdFromChangeStream`** (ObjectId or string).
   - **Upserts:** **`elasticsearchUpsert`** → **`ElasticsearchService.save(index, id, json)`** for `insert` / `update` / `replace`.
4. **Elasticsearch index name** per collection is **`getElasticsearchIndexName()`** on the enum (e.g. `transaction` → `txn`, `contactEmail` → `contactemail`, otherwise lowercase Mongo collection name).

## `EsSyncCollection` contract

| Field / method | Role |
|----------------|------|
| **`syncToEs`** | If `false`, **no** ES writes for that collection (e.g. `user`, `Webhook`, `Session`). |
| **`clazz`** | Entity class (used by `DeltaSyncService` and selective mappers). |
| **`processingStrategy`** | How **`MongoEventConsumer`** handles non-delete events (raw JSON, selective ES, contacts/transaction pipelines, webhook, session). |

Strategies include **`RAW_ELASTICSEARCH`**, **`SELECTIVE_*`**, **`CONTACTS_PIPELINE`**, **`TRANSACTION_PIPELINE`**, **`WEBHOOK_HANDLER`**, **`SESSION_HANDLER`**.

## Specialized paths (still in `MongoEventConsumer`)

- **contacts** — selective deserialization, tag/list resolution, contact activity, automation, audit.
- **campaign / automation / contactEmail** — selective JSON with fallback to full document on error.
- **transaction** — enrich from contact, automation, audit; index **`txn`**.
- **Webhook** — **`WebhookHandlerService`** only (no ES).
- **Session** — audit + shop import hook (no ES).

## Implications for changes

- New watched collection: add an **`EsSyncCollection`** constant (name = Mongo collection), set **`syncToEs`** and **`processingStrategy`**, implement or reuse a branch in **`MongoEventConsumer`** if not **`RAW_ELASTICSEARCH`** or selective-only.
- Do not rely on stringly `switch` on collection names outside the enum lookup; **`fromMongoCollectionName`** is the single entry point.

## Reference files

| Piece | Location |
|-------|----------|
| Enum + strategies + index names | `service/src/main/java/com/goveca/recruit/ad/enums/EsSyncCollection.java` |
| Change stream bootstrap | `service/src/main/java/com/goveca/MongoConfiguration.java` |
| Event handling & ES writes | `service/src/main/java/com/goveca/MongoEventConsumer.java` |
| ES client operations | `service/src/main/java/com/goveca/recruit/ad/service/ElasticsearchService.java` |
