package service

import (
	"errors"
	"djapp3/backend/internal/dto"
	"djapp3/backend/internal/model"
	"djapp3/backend/internal/repository"
)

type OrgService struct {
	repo *repository.OrgRepository
}

func NewOrgService(repo *repository.OrgRepository) *OrgService {
	return &OrgService{repo: repo}
}

func (s *OrgService) List(orgID uint) ([]model.Org, error) {
	return s.repo.List(orgID)
}

func (s *OrgService) GetByID(id uint) (*model.Org, error) {
	return s.repo.GetByID(id)
}

func (s *OrgService) Create(req dto.OrgRequest) (*model.Org, error) {
	if req.ParentID == nil && req.Level != 1 {
		return nil, errors.New("顶级组织的层级必须为1")
	}
	if req.ParentID != nil && req.Level == 1 {
		return nil, errors.New("非顶级组织的层级不能为1")
	}
	item := &model.Org{
		Name:     req.Name,
		ParentID: req.ParentID,
		Level:    req.Level,
		OrgType:  req.OrgType,
	}
	if err := s.repo.Create(item); err != nil {
		return nil, err
	}
	return item, nil
}

func (s *OrgService) Update(id uint, req dto.OrgRequest) (*model.Org, error) {
	if req.ParentID == nil && req.Level != 1 {
		return nil, errors.New("顶级组织的层级必须为1")
	}
	if req.ParentID != nil && req.Level == 1 {
		return nil, errors.New("非顶级组织的层级不能为1")
	}
	item, err := s.repo.GetByID(id)
	if err != nil {
		return nil, err
	}
	item.Name = req.Name
	item.ParentID = req.ParentID
	item.Level = req.Level
	item.OrgType = req.OrgType
	if err := s.repo.Update(item); err != nil {
		return nil, err
	}
	return item, nil
}

func (s *OrgService) Delete(id uint) error {
	return s.repo.Delete(id)
}
