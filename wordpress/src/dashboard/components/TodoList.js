import React, { useEffect, useState } from "react";
import { ReactComponent as IconTick } from "../../assets/icons/icon-tick.svg";
import Progress from "../../components/Progress";
import {
  TASK_ID_TO_STATUS,
  TODO_TASKS,
} from "../../constants/todoTasks";
import { apiClient, getAppBaseUrl } from "../../utils";

function updateCompletedByStatus(tasks, status) {
  return tasks.map((task) => {
    const statusKey = TASK_ID_TO_STATUS[task.id];
    if (statusKey && status[statusKey] !== undefined) {
      return { ...task, completed: Boolean(status[statusKey]) };
    }
    return task;
  });
}

const TodoList = ({ onApiError }) => {
  const [tasks, setTasks] = useState(() =>
    TODO_TASKS.map((task) => ({ ...task, completed: false })),
  );
  const [isLoading, setIsLoading] = useState(false);
  const [isHidden, setIsHidden] = useState(false);
  const [showDismiss, setShowDismiss] = useState(false);

  const fetchTodo = () => {
    setIsLoading(true);
    apiClient("proxy/todo")
      .then((data) => {
        setTasks((prev) => updateCompletedByStatus(prev, data));

        const {
          dismissTodoSection,
          isBusinessDetailsAvailable,
          isContactCreated,
          isDomainConnected,
          isStoreConnected,
        } = data;

        const allComplete =
          isBusinessDetailsAvailable &&
          isContactCreated &&
          isDomainConnected &&
          isStoreConnected;

        setIsHidden(
          Boolean(
            dismissTodoSection &&
              isBusinessDetailsAvailable &&
              isContactCreated &&
              isDomainConnected &&
              isStoreConnected,
          ),
        );
        setShowDismiss(Boolean(allComplete));
      })
      .catch((error) => {
        console.log("Error fetching todo list:", error);
        onApiError?.(error);
      })
      .finally(() => setIsLoading(false));
  };

  useEffect(() => {
    fetchTodo();
  }, []);

  const dismissTodo = () => {
    apiClient("proxy/users/preferences", "PUT", null, {
      dismissTodoSection: true,
    })
      .then(() => fetchTodo())
      .catch((error) => {
        console.log("Error dismissing todo list:", error);
        onApiError?.(error);
      });
  };

  const openTaskInApp = (path) => {
    window.open(`${getAppBaseUrl()}/app/${path}`, "_blank");
  };

  if (isHidden) {
    return null;
  }

  const completedTasks = tasks.filter((task) => task.completed).length;
  const totalTasks = tasks.length;

  return (
    <div className="todo-container">
      <div className="todo-list-header">
        <h3>Todo List</h3>
        {isLoading && <Progress />}
        <div className="task-progress">
          <span>
            {completedTasks} of {totalTasks} tasks
          </span>
          <div className="progress-bar">
            <div
              className="progress-bar-fill"
              style={{ width: `${(completedTasks / totalTasks) * 100}%` }}
            />
          </div>
          {showDismiss && (
            <button
              type="button"
              onClick={dismissTodo}
              className="btn btn-noBGTextWhite"
            >
              Dismiss
            </button>
          )}
        </div>
      </div>
      <div className="todo-items">
        {tasks.map((task) => (
          <div className="todo-item" key={task.id}>
            <div className="todo-left">
              {task.completed ? (
                <div className="task-icon-completed">
                  <IconTick className="icon24" />
                </div>
              ) : (
                <div className="task-icon" />
              )}
              <div className="task-details">
                <h4 className="task-title">{task.title}</h4>
                <p className="task-description">{task.description}</p>
              </div>
            </div>
            {!task.completed && (
              <div className="todo-right">
                <button
                  type="button"
                  onClick={() => openTaskInApp(task.path)}
                  className="task-button"
                >
                  {task.actionLabel}
                </button>
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
};

export default TodoList;
