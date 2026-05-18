import React from "react";
import { TODO_TASKS } from "../../constants/todoTasks";

const TodoListPreview = ({ onTaskAction }) => {
  const completedTasks = 0;
  const totalTasks = TODO_TASKS.length;

  return (
    <div className="todo-container">
      <div className="todo-list-header">
        <h3>Todo List</h3>
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
        </div>
      </div>
      <div className="todo-items">
        {TODO_TASKS.map((task) => (
          <div className="todo-item" key={task.id}>
            <div className="todo-left">
              <div className="task-icon" />
              <div className="task-details">
                <h4 className="task-title">{task.title}</h4>
                <p className="task-description">{task.description}</p>
              </div>
            </div>
            <div className="todo-right">
              <button
                type="button"
                onClick={() => onTaskAction?.()}
                className="task-button"
              >
                {task.actionLabel}
              </button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

export default TodoListPreview;
