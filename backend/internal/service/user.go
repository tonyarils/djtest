package service

import (
	"djapp3/backend/internal/dto"
	"djapp3/backend/internal/model"
	"djapp3/backend/internal/repository"
)

type UserService struct {
	repo *repository.UserRepository
}

func NewUserService(repo *repository.UserRepository) *UserService {
	return &UserService{repo: repo}
}

func (s *UserService) List(orgID uint) ([]model.User, error) {
	return s.repo.List(orgID)
}

func (s *UserService) GetByID(id uint) (*model.User, error) {
	return s.repo.GetByID(id)
}

func (s *UserService) Create(req dto.UserRequest) (*model.User, error) {
	item := &model.User{
		Name:       req.Name,
		EmployeeNo: req.EmployeeNo,
		OrgID:      req.OrgID,
		PartyRole:  req.PartyRole,
		JobTitle:   req.JobTitle,
		Gender:     req.Gender,
		Education:  req.Education,
		Remark:     req.Remark,
	}
	if err := s.repo.Create(item); err != nil {
		return nil, err
	}
	return item, nil
}

func (s *UserService) Update(id uint, req dto.UserRequest) (*model.User, error) {
	item, err := s.repo.GetByID(id)
	if err != nil {
		return nil, err
	}
	item.Name = req.Name
	item.EmployeeNo = req.EmployeeNo
	item.OrgID = req.OrgID
	item.PartyRole = req.PartyRole
	item.JobTitle = req.JobTitle
	item.Gender = req.Gender
	item.Education = req.Education
	item.Remark = req.Remark
	if err := s.repo.Update(item); err != nil {
		return nil, err
	}
	return item, nil
}

func (s *UserService) Delete(id uint) error {
	return s.repo.Delete(id)
}
