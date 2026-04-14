package repository

import (
	"djapp3/backend/internal/model"

	"gorm.io/gorm"
)

type TaskRepository struct {
	db *gorm.DB
}

func NewTaskRepository(db *gorm.DB) *TaskRepository {
	return &TaskRepository{db: db}
}

func (r *TaskRepository) List(orgID uint) ([]model.Task, error) {
	var items []model.Task
	query := r.db.Model(&model.Task{})
	if orgID != 0 {
		query = query.Where("org_id = ?", orgID)
	}
	err := query.Order("id desc").Find(&items).Error
	return items, err
}

func (r *TaskRepository) GetByID(id uint) (*model.Task, error) {
	var item model.Task
	if err := r.db.First(&item, id).Error; err != nil {
		return nil, err
	}
	return &item, nil
}

func (r *TaskRepository) Create(item *model.Task) error {
	return r.db.Create(item).Error
}

func (r *TaskRepository) Update(item *model.Task) error {
	return r.db.Save(item).Error
}

func (r *TaskRepository) Delete(id uint) error {
	return r.db.Delete(&model.Task{}, id).Error
}
