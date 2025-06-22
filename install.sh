#!/usr/bin/env bash
# install.sh — préparation d’un poste de dev ou d’exécution locale
# Usage : ./install.sh [user] [database] [password]

set -e  # stop au premier souci
PROJECT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
DB_USER="${1:-root}"
DB_NAME="${2:-asbh}"
DB_PWD="${3:-}"

echo "📦 Création de l'environnement virtuel…"
python3 -m venv "$PROJECT_DIR/.venv"
source "$PROJECT_DIR/.venv/bin/activate"
python -m pip install --upgrade pip wheel
pip install -r "$PROJECT_DIR/requirements.txt"

echo "🐬 Installation ou vérification de MySQL (sudo requis)…"
if ! command -v mysql >/dev/null 2>&1; then
  sudo apt-get update
  sudo apt-get install -y mysql-server libmysqlclient-dev
  sudo systemctl enable --now mysql
fi

echo "🔑 Initialisation de la base « $DB_NAME »…"
if [ -z "$DB_PWD" ]; then
  echo "⚠️ Mot de passe MySQL non fourni : on suppose un accès root local sans mot de passe."
  mysql -u"$DB_USER" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4;"
  mysql -u"$DB_USER" "$DB_NAME" < "$PROJECT_DIR/application_asbh(3).sql"
else
  mysql -u"$DB_USER" -p"$DB_PWD" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4;"
  mysql -u"$DB_USER" -p"$DB_PWD" "$DB_NAME" < "$PROJECT_DIR/application_asbh(3).sql"
fi

echo "🗝️ Création du fichier .env (si absent)…"
ENV_FILE="$PROJECT_DIR/.env"
if [ ! -f "$ENV_FILE" ]; then
  cat <<EOF > "$ENV_FILE"
FLASK_ENV=development
MYSQL_USER=$DB_USER
MYSQL_PASSWORD=$DB_PWD
MYSQL_DB=$DB_NAME
MYSQL_HOST=localhost
OLLAMA_BASE=http://localhost:11434
EOF
fi

echo "✅ Installation terminée."
echo "➡️ Active l'environnement :   source .venv/bin/activate"
echo "➡️ Lance l’API Flask :       python app.py"
