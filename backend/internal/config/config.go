package config

import (
	"fmt"
	"os"
)

type Config struct {
	AppEnv      string
	AppPort     string
	DBHost      string
	DBPort      string
	DBName      string
	DBUser      string
	DBPassword  string
	AutoMigrate bool
	AutoSeed    bool
}

func Load() Config {
	cfg := Config{
		AppEnv:      getEnv("APP_ENV", "development"),
		AppPort:     getEnv("APP_PORT", "8080"),
		DBHost:      getEnv("DB_HOST", "172.23.72.148"),
		DBPort:      getEnv("DB_PORT", "3306"),
		DBName:      getEnv("DB_NAME", "party_db"),
		DBUser:      getEnv("DB_USER", "djapp"),
		DBPassword:  getEnv("DB_PASSWORD", "Wmjf2la!"),
		AutoMigrate: getEnv("AUTO_MIGRATE", "true") == "true",
		AutoSeed:    getEnv("AUTO_SEED", "true") == "true",
	}

	return cfg
}

func (c Config) DSN() string {
	return fmt.Sprintf("%s:%s@tcp(%s:%s)/%s?charset=utf8mb4&parseTime=True&loc=Local", c.DBUser, c.DBPassword, c.DBHost, c.DBPort, c.DBName)
}

func getEnv(key, fallback string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return fallback
}
