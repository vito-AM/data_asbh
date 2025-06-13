from flask import Flask, Blueprint, render_template, request, redirect, url_for, flash, jsonify
from flask_sqlalchemy import SQLAlchemy
import os

app = Flask(__name__)
app.config['SQLALCHEMY_DATABASE_URI'] = 'mysql+pymysql://root:root@localhost:3306/application_asbh'
app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = False
db = SQLAlchemy(app)

UPLOAD_FOLDER = 'app/static/uploads/'



# === DEPOT ===
depot_bp = Blueprint('depot', __name__)

@depot_bp.route('/depot', methods=['GET', 'POST'])
def depot():
    if request.method == 'POST':
        file = request.files['file']
        if file:
            file_path = os.path.join(UPLOAD_FOLDER, file.filename)
            file.save(file_path)

            try:
                from import_to_db import import_excel_to_db
                import_excel_to_db(file_path)
                flash("Fichier traité et importé avec succès !", "success")
            except Exception as e:
                flash(f"Erreur lors de l'import : {e}", "danger")

            return redirect(url_for('depot.depot'))

    return render_template('depot.html')

app.register_blueprint(depot_bp)

# === NOUVELLE ROUTE API ===
@app.route('/import-excel', methods=['POST'])
def import_excel_files():
    from import_to_db import import_excel_to_db
    if not request.is_json:
        return jsonify({'error': 'Contenu attendu : JSON'}), 400

    data = request.get_json()
    file_paths = data.get('files', [])

    if not isinstance(file_paths, list) or not file_paths:
        return jsonify({'error': 'La liste des fichiers est manquante ou vide.'}), 400

    imported = []
    errors = []

    for file_path in file_paths:
        abs_path = os.path.abspath(file_path)
        if not os.path.isfile(abs_path):
            errors.append(f"Fichier non trouvé : {file_path}")
            continue

        try:
            import_excel_to_db(abs_path)
            imported.append(file_path)
        except Exception as e:
            errors.append(f"Erreur pour {file_path} : {str(e)}")

    return jsonify({
        'imported': imported,
        'errors': errors
    })

if __name__ == "__main__":
    app.run(debug=True, host='127.0.0.1', port=5000)