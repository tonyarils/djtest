package repository

import (
	"djapp3/backend/internal/model"

	"gorm.io/gorm"
)

type OrgRepository struct {
	db *gorm.DB
}

func NewOrgRepository(db *gorm.DB) *OrgRepository {
	return &OrgRepository{db: db}
}

func (r *OrgRepository) List(orgID uint) ([]model.Org, error) {
	var items []model.Org
	query := r.db.Model(&model.Org{})
	if orgID != 0 {
		query = query.Where("id = ? OR parent_id = ?", orgID, orgID)
	}
	err := query.Order("id desc").Find(&items).Error
	return items, err
}

func (r *OrgRepository) GetByID(id uint) (*model.Org, error) {
	var item model.Org
	if err := r.db.First(&item, id).Error; err != nil {
		return nil, err
	}
	return &item, nil
}

func (r *OrgRepository) Create(item *model.Org) error {
	return r.db.Create(item).Error
}

func (r *OrgRepository) Update(item *model.Org) error {
	return r.db.Save(item).Error
}

func (r *OrgRepository) Delete(id uint) error {
	return r.db.Delete(&model.Org{}, id).Error
}
