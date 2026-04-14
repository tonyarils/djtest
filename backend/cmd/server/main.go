package main

import (
	"log"

	"djapp3/backend/internal/bootstrap"
	"djapp3/backend/internal/config"
	"djapp3/backend/internal/handler"
	"djapp3/backend/internal/repository"
	"djapp3/backend/internal/router"
	"djapp3/backend/internal/service"

	"gorm.io/driver/mysql"
	"gorm.io/gorm"
)

func main() {
	cfg := config.Load()

	db, err := gorm.Open(mysql.Open(cfg.DSN()), &gorm.Config{})
	if err != nil {
		log.Fatalf("connect database failed: %v", err)
	}

	if cfg.AutoMigrate {
		if err := bootstrap.Migrate(db); err != nil {
			log.Fatalf("auto migrate failed: %v", err)
		}
	}
	if cfg.AutoSeed {
		if err := bootstrap.Seed(db); err != nil {
			log.Fatalf("seed database failed: %v", err)
		}
	}

	orgRepo := repository.NewOrgRepository(db)
	userRepo := repository.NewUserRepository(db)
	taskRepo := repository.NewTaskRepository(db)

	orgService := service.NewOrgService(orgRepo)
	userService := service.NewUserService(userRepo)
	taskService := service.NewTaskService(taskRepo)

	orgHandler := handler.NewOrgHandler(orgService)
	userHandler := handler.NewUserHandler(userService)
	taskHandler := handler.NewTaskHandler(taskService)

	engine := router.New(orgHandler, userHandler, taskHandler)
	if err := engine.Run(":" + cfg.AppPort); err != nil {
		log.Fatalf("start server failed: %v", err)
	}
}
