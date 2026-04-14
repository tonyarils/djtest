package middleware

import (
	"strconv"

	"github.com/gin-gonic/gin"
)

const (
	ContextUserIDKey = "current_user_id"
	ContextOrgIDKey  = "current_org_id"
)

func MockAuthContext() gin.HandlerFunc {
	return func(c *gin.Context) {
		userID, _ := strconv.ParseUint(c.GetHeader("X-User-ID"), 10, 64)
		orgID, _ := strconv.ParseUint(c.GetHeader("X-Org-ID"), 10, 64)

		c.Set(ContextUserIDKey, uint(userID))
		c.Set(ContextOrgIDKey, uint(orgID))
		c.Next()
	}
}

func CurrentOrgID(c *gin.Context) uint {
	value, exists := c.Get(ContextOrgIDKey)
	if !exists {
		return 0
	}
	orgID, ok := value.(uint)
	if !ok {
		return 0
	}
	return orgID
}
