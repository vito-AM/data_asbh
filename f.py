import pandas as pd
import glob

# Dossier contenant les fichiers
folder = "uploads/"

# Chercher tous les fichiers Excel correspondants au motif
pattern = f"{folder}*Data Match*_modifie.xlsx"
files = glob.glob(pattern)

if not files:
    print("❌ Aucun fichier correspondant au motif 'Data Match*_modifie.xlsx' trouvé.")
else:
    for file in files:
        print(f"\n📁 Fichier : {file}")
        try:
            xl = pd.ExcelFile(file)
            sheet_names = xl.sheet_names
            print(f"  📄 Feuilles disponibles : {', '.join(sheet_names)}")
            
            for sheet in sheet_names:
                print(f"    🔍 Colonnes de la feuille '{sheet}' :")
                try:
                    df = xl.parse(sheet)
                    print(f"      {list(df.columns)}")
                except Exception as e:
                    print(f"      ⚠️ Erreur lors de la lecture de la feuille '{sheet}': {e}")
        
        except Exception as e:
            print(f"❌ Erreur de lecture du fichier {file} : {e}")
