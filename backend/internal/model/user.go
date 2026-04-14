package model

type User struct {
	BaseModel
	Name       string `gorm:"column:name;size:100;not null" json:"name"`
	EmployeeNo string `gorm:"column:employee_no;size:50;not null;uniqueIndex" json:"employee_no"`
	OrgID      uint   `gorm:"column:org_id;not null;index" json:"org_id"`
	PartyRole  string `gorm:"column:party_role;size:100" json:"party_role"`
	JobTitle   string `gorm:"column:job_title;size:100" json:"job_title"`
	Gender     string `gorm:"column:gender;size:20" json:"gender"`
	Education  string `gorm:"column:education;size:50" json:"education"`
	Remark     string `gorm:"column:remark;size:255" json:"remark"`
}

func (User) TableName() string {
	return "sys_user"
}
