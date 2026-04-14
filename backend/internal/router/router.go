package router

import (
	"djapp3/backend/internal/handler"
	"djapp3/backend/internal/middleware"

	"github.com/gin-gonic/gin"
)

func New(orgHandler *handler.OrgHandler, userHandler *handler.UserHandler, taskHandler *handler.TaskHandler) *gin.Engine {
	r := gin.Default()
	r.Use(middleware.MockAuthContext())

	r.GET("/healthz", func(c *gin.Context) {
		c.JSON(200, gin.H{"message": "ok"})
	})

	api := r.Group("/api")
	{
		api.GET("/orgs", orgHandler.List)
		api.GET("/orgs/:id", orgHandler.Get)
		api.POST("/orgs", orgHandler.Create)
		api.PUT("/orgs/:id", orgHandler.Update)
		api.DELETE("/orgs/:id", orgHandler.Delete)

		api.GET("/users", userHandler.List)
		api.GET("/users/:id", userHandler.Get)
		api.POST("/users", userHandler.Create)
		api.PUT("/users/:id", userHandler.Update)
		api.DELETE("/users/:id", userHandler.Delete)

		api.GET("/tasks", taskHandler.List)
		api.GET("/tasks/:id", taskHandler.Get)
		api.POST("/tasks", taskHandler.Create)
		api.PUT("/tasks/:id", taskHandler.Update)
		api.DELETE("/tasks/:id", taskHandler.Delete)
	}

	return r
}
