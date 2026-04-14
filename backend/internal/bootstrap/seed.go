package bootstrap

import (
	"time"

	"djapp3/backend/internal/model"

	"gorm.io/gorm"
)

func Seed(db *gorm.DB) error {
	var orgCount int64
	if err := db.Model(&model.Org{}).Count(&orgCount).Error; err != nil {
		return err
	}
	if orgCount > 0 {
		return nil
	}

	党委 := model.Org{Name: "示范党委", Level: 1, OrgType: "党委"}
	if err := db.Create(&党委).Error; err != nil {
		return err
	}

	市级党委 := model.Org{Name: "市级党委", Level: 1, OrgType: "党委"}
	if err := db.Create(&市级党委).Error; err != nil {
		return err
	}

	支部 := model.Org{Name: "第一党支部", ParentID: &党委.ID, Level: 2, OrgType: "支部"}
	if err := db.Create(&支部).Error; err != nil {
		return err
	}

	第二支部 := model.Org{Name: "第二党支部", ParentID: &党委.ID, Level: 2, OrgType: "支部"}
	if err := db.Create(&第二支部).Error; err != nil {
		return err
	}

	市级支部 := model.Org{Name: "市级第一党支部", ParentID: &市级党委.ID, Level: 2, OrgType: "支部"}
	if err := db.Create(&市级支部).Error; err != nil {
		return err
	}

	党小组 := model.Org{Name: "第一党小组", ParentID: &支部.ID, Level: 3, OrgType: "党小组"}
	if err := db.Create(&党小组).Error; err != nil {
		return err
	}

	党员 := model.User{
		Name:       "张三",
		EmployeeNo: "DJ001",
		OrgID:      支部.ID,
		PartyRole:  "组织委员",
		JobTitle:   "专员",
		Gender:     "男",
		Education:  "本科",
		Remark:     "系统初始化示例数据",
	}
	if err := db.Create(&党员).Error; err != nil {
		return err
	}

	党员2 := model.User{
		Name:       "李四",
		EmployeeNo: "DJ002",
		OrgID:      第二支部.ID,
		PartyRole:  "宣传委员",
		JobTitle:   "助理",
		Gender:     "女",
		Education:  "硕士",
		Remark:     "测试数据",
	}
	if err := db.Create(&党员2).Error; err != nil {
		return err
	}

	党员3 := model.User{
		Name:       "王五",
		EmployeeNo: "DJ003",
		OrgID:      党小组.ID,
		PartyRole:  "小组长",
		JobTitle:   "经理",
		Gender:     "男",
		Education:  "大专",
		Remark:     "测试数据",
	}
	if err := db.Create(&党员3).Error; err != nil {
		return err
	}

	deadline := time.Now().Add(72 * time.Hour)
	任务 := model.Task{
		Title:        "示例党建任务",
		Description:  "用于验证系统初始化后的任务列表展示",
		TaskType:     "A",
		Status:       "待领用",
		WarningLevel: 0,
		OrgID:        支部.ID,
		AssigneeID:   &党员.ID,
		DeadlineAt:   &deadline,
	}
	if err := db.Create(&任务).Error; err != nil {
		return err
	}

	deadline2 := time.Now().Add(48 * time.Hour)
	任务2 := model.Task{
		Title:        "学习党章任务",
		Description:  "组织党员学习最新党章内容",
		TaskType:     "B",
		Status:       "进行中",
		WarningLevel: 1,
		OrgID:        第二支部.ID,
		AssigneeID:   &党员2.ID,
		DeadlineAt:   &deadline2,
	}
	if err := db.Create(&任务2).Error; err != nil {
		return err
	}

	deadline3 := time.Now().Add(96 * time.Hour)
	任务3 := model.Task{
		Title:        "志愿服务活动",
		Description:  "组织社区志愿服务活动",
		TaskType:     "C",
		Status:       "已完成",
		WarningLevel: 0,
		OrgID:        党小组.ID,
		AssigneeID:   &党员3.ID,
		DeadlineAt:   &deadline3,
	}
	return db.Create(&任务3).Error
}
