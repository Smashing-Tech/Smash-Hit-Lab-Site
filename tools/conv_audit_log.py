import json
import pathlib

data = json.loads(pathlib.Path("audit_log.json").read_text())

entries = data["notifications"]

for e in entries:
	print(e["title"])
