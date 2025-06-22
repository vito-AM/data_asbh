@echo off
REM ───────────────────────────────────────────────
REM  setup.bat — installation locale sous Windows
REM  Usage :  setup.bat [user] [database] [password]
REM           (tout est optionnel : root / asbh / <vide>)
REM ───────────────────────────────────────────────

SETLOCAL ENABLEDELAYEDEXPANSION
SET "DB_USER=%~1"
IF "%DB_USER%"=="" SET "DB_USER=root"

SET "DB_NAME=%~2"
IF "%DB_NAME%"=="" SET "DB_NAME=application_asbh"

SET "DB_PWD=%~3"

REM ——— Chemins utiles
SET "PROJ_DIR=%~dp0"
SET "VENV_DIR=%PROJ_DIR%.venv"

ECHO 📦  Création de l’environnement virtuel…
python -m venv "%VENV_DIR%"
CALL "%VENV_DIR%\Scripts\activate.bat"
python -m pip install --upgrade pip wheel
pip install -r "%PROJ_DIR%requirements.txt"

REM ——— Test MySQL
ECHO 🐬  Vérification de MySQL…
where mysql >nul 2>&1
IF ERRORLEVEL 1 (
    ECHO ⛔  MySQL n’est pas installé !
    ECHO     Installe d’abord MySQL Community Server (8.x) puis relance ce script.
    GOTO :EOF
)

REM ——— Création base + import dump
ECHO 🔑  Initialisation de la base "%DB_NAME%"…
IF "%DB_PWD%"=="" (
    mysql -u"%DB_USER%" -e "CREATE DATABASE IF NOT EXISTS %DB_NAME% CHARACTER SET utf8mb4;"
    mysql -u"%DB_USER%" "%DB_NAME%" < "%PROJ_DIR%application_asbh.sql"
) ELSE (
    mysql -u"%DB_USER%" -p"%DB_PWD%" -e "CREATE DATABASE IF NOT EXISTS %DB_NAME% CHARACTER SET utf8mb4;"
    mysql -u"%DB_USER%" -p"%DB_PWD%" "%DB_NAME%" < "%PROJ_DIR%application_asbh.sql"
)

REM ——— Fichier .env
SET "ENV_FILE=%PROJ_DIR%.env"
IF NOT EXIST "%ENV_FILE%" (
    ECHO 🗝️  Création du .env…
    (
        ECHO FLASK_ENV=development
        ECHO MYSQL_USER=%DB_USER%
        ECHO MYSQL_PASSWORD=%DB_PWD%
        ECHO MYSQL_DB=%DB_NAME%
        ECHO MYSQL_HOST=localhost
        ECHO OLLAMA_BASE=http://localhost:11434
    ) > "%ENV_FILE%"
)

ECHO.
ECHO ✅  Installation terminée !
ECHO --------------------------------------------------------
ECHO  Pour démarrer :
ECHO    CALL "%VENV_DIR%\Scripts\activate.bat"
ECHO    python app.py
ECHO --------------------------------------------------------
ENDLOCAL
