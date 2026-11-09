#!/bin/sh
# Runs once on a fresh pgdata volume: creates the isolated database used by the API test suite.
set -eu
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-SQL
  CREATE DATABASE app_test OWNER "$POSTGRES_USER";
SQL
