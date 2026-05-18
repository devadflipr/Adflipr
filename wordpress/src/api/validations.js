import * as zod from "zod";

const loginSchema = zod.object({
  email: zod
    .string()
    .email({ message: "Please enter a valid email address" })
    .min(1, { message: "Email is required" }),
  password: zod
    .string()
    .min(6, { message: "Password must be at least 6 characters" })
    .min(1, { message: "Password is required" }),
});

export { loginSchema };
