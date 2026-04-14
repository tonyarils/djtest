package dto

type TaskRequest struct {
	Title       string `json:"title" binding:"required"`
	Description string `json:"description"`
	TaskType    string `json:"task_type" binding:"required"`
	Status      string `json:"status" binding:"required"`
	OrgID       uint   `json:"org_id" binding:"required"`
	AssigneeID  *uint  `json:"assignee_id"`
	DeadlineAt  string `json:"deadline_at"`
}

type TaskResponse struct {
	ID          uint   `json:"id"`
	Title       string `json:"title"`
	Description string `json:"description"`
	TaskType    string `json:"task_type"`
	Status      string `json:"status"`
	WarningLevel int   `json:"warning_level"`
	OrgID       uint   `json:"org_id"`
	AssigneeID  *uint  `json:"assignee_id"`
	DeadlineAt  string `json:"deadline_at"`
}
