package service

import (
	"time"

	"djapp3/backend/internal/dto"
	"djapp3/backend/internal/model"
	"djapp3/backend/internal/repository"
)

type TaskService struct {
	repo *repository.TaskRepository
}

func NewTaskService(repo *repository.TaskRepository) *TaskService {
	return &TaskService{repo: repo}
}

func (s *TaskService) List(orgID uint) ([]model.Task, error) {
	return s.repo.List(orgID)
}

func (s *TaskService) GetByID(id uint) (*model.Task, error) {
	return s.repo.GetByID(id)
}

func (s *TaskService) Create(req dto.TaskRequest) (*model.Task, error) {
	deadlineAt, err := parseDeadline(req.DeadlineAt)
	if err != nil {
		return nil, err
	}

	item := &model.Task{
		Title:        req.Title,
		Description:  req.Description,
		TaskType:     req.TaskType,
		Status:       req.Status,
		WarningLevel: 0,
		OrgID:        req.OrgID,
		AssigneeID:   req.AssigneeID,
		DeadlineAt:   deadlineAt,
	}
	if err := s.repo.Create(item); err != nil {
		return nil, err
	}
	return item, nil
}

func (s *TaskService) Update(id uint, req dto.TaskRequest) (*model.Task, error) {
	item, err := s.repo.GetByID(id)
	if err != nil {
		return nil, err
	}
	deadlineAt, err := parseDeadline(req.DeadlineAt)
	if err != nil {
		return nil, err
	}
	item.Title = req.Title
	item.Description = req.Description
	item.TaskType = req.TaskType
	item.Status = req.Status
	item.OrgID = req.OrgID
	item.AssigneeID = req.AssigneeID
	item.DeadlineAt = deadlineAt
	if err := s.repo.Update(item); err != nil {
		return nil, err
	}
	return item, nil
}

func (s *TaskService) Delete(id uint) error {
	return s.repo.Delete(id)
}

func parseDeadline(value string) (*time.Time, error) {
	if value == "" {
		return nil, nil
	}
	parsed, err := time.Parse(time.RFC3339, value)
	if err != nil {
		return nil, err
	}
	return &parsed, nil
}
