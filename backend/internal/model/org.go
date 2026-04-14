package model

type Org struct {
	BaseModel
	Name     string `gorm:"column:name;size:100;not null" json:"name"`
	ParentID *uint  `gorm:"column:parent_id" json:"parent_id"`
	Level    int    `gorm:"column:level;not null" json:"level"`
	OrgType  string `gorm:"column:org_type;size:50;not null" json:"org_type"`
}

func (Org) TableName() string {
	return "sys_org"
}
