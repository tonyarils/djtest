package repository

import (
	"djapp3/backend/internal/model"

	"gorm.io/gorm"
)

type UserRepository struct {
	db *gorm.DB
}

func NewUserRepository(db *gorm.DB) *UserRepository {
	return &UserRepository{db: db}
}

func (r *UserRepository) List(orgID uint) ([]model.User, error) {
	var items []model.User
	query := r.db.Model(&model.User{})
	if orgID != 0 {
		query = query.Where("org_id = ?", orgID)
	}
	err := query.Order("id desc").Find(&items).Error
	return items, err
}

func (r *UserRepository) GetByID(id uint) (*model.User, error) {
	var item model.User
	if err := r.db.First(&item, id).Error; err != nil {
		return nil, err
	}
	return &item, nil
}

func (r *UserRepository) Create(item *model.User) error {
	return r.db.Create(item).Error
}

func (r *UserRepository) Update(item *model.User) error {
	return r.db.Save(item).Error
}

func (r *UserRepository) Delete(id uint) error {
	return r.db.Delete(&model.User{}, id).Error
}
