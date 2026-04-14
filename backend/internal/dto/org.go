package dto

type OrgRequest struct {
	Name     string `json:"name" binding:"required"`
	ParentID *uint  `json:"parent_id"`
	Level    int    `json:"level" binding:"required"`
	OrgType  string `json:"org_type" binding:"required"`
}

type OrgResponse struct {
	ID       uint   `json:"id"`
	Name     string `json:"name"`
	ParentID *uint  `json:"parent_id"`
	Level    int    `json:"level"`
	OrgType  string `json:"org_type"`
}
