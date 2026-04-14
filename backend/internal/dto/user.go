package dto

type UserRequest struct {
	Name         string `json:"name" binding:"required"`
	EmployeeNo   string `json:"employee_no" binding:"required"`
	OrgID        uint   `json:"org_id" binding:"required"`
	PartyRole    string `json:"party_role"`
	JobTitle     string `json:"job_title"`
	Gender       string `json:"gender"`
	Education    string `json:"education"`
	Remark       string `json:"remark"`
}

type UserResponse struct {
	ID         uint   `json:"id"`
	Name       string `json:"name"`
	EmployeeNo string `json:"employee_no"`
	OrgID      uint   `json:"org_id"`
	PartyRole  string `json:"party_role"`
	JobTitle   string `json:"job_title"`
	Gender     string `json:"gender"`
	Education  string `json:"education"`
	Remark     string `json:"remark"`
}
