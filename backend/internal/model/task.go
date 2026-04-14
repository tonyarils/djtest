package model

import "time"

type Task struct {
	BaseModel
	Title        string     `gorm:"column:title;size:200;not null" json:"title"`
	Description  string     `gorm:"column:description;type:text" json:"description"`
	TaskType     string     `gorm:"column:task_type;size:20;not null" json:"task_type"`
	Status       string     `gorm:"column:status;size:20;not null" json:"status"`
	WarningLevel int        `gorm:"column:warning_level;not null;default:0" json:"warning_level"`
	OrgID        uint       `gorm:"column:org_id;not null;index" json:"org_id"`
	AssigneeID   *uint      `gorm:"column:assignee_id;index" json:"assignee_id"`
	DeadlineAt   *time.Time `gorm:"column:deadline_at" json:"deadline_at"`
}

func (Task) TableName() string {
	return "t_task"
}
