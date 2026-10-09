from dotenv import load_dotenv
import os
from pathlib import Path

# Load .env if exists
load_dotenv()

# 👇 Resolve BASE_DIR — this is the project root folder
BASE_DIR = Path(__file__).resolve().parent.parent.parent

class Settings:
    BASE_URL: str = os.getenv("BASE_URL", "http://localhost:5000")
    UPLOAD_DIR: Path = BASE_DIR / os.getenv("UPLOAD_DIR", "data/uploads")
    CONVERTED_DIR: Path = BASE_DIR / os.getenv("CONVERTED_DIR", "data/converted")
    TEMPLATES_DIR: Path = BASE_DIR / os.getenv("TEMPLATES_DIR", "app/templates")


settings = Settings()

# 👇 Make sure these directories exist
for path in [
    settings.UPLOAD_DIR,
    settings.CONVERTED_DIR,
    settings.TEMPLATES_DIR
]:
    os.makedirs(path, exist_ok=True)
