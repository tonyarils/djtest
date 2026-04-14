package bootstrap

import (
	"djapp3/backend/internal/model"

	"gorm.io/gorm"
)

func Migrate(db *gorm.DB) error {
	return db.AutoMigrate(
		&model.Org{},
		&model.User{},
		&model.Task{},
	)
}
