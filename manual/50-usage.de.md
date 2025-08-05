---
title: Verwendung
---
# Verwendung des Plugins

## Admin-Oberfläche
1. Zu Topdata Demo Data im Admin-Menü navigieren
2. Auf "Demo-Daten importieren" klicken, um gebündelte Produkte zu importieren

## Konsolenbefehle
### Demo-Produkte importieren
```bash
bin/console topdata:demo-data-importer:import-demo-products [--category-id=ID] [--no-category] [--force]
```

### Demo-Produkte entfernen
```bash
bin/console topdata:demo-data-importer:remove-demo-products [--force]
```

### Webservice-Zugangsdaten einrichten
```bash
bin/console topdata:demo-data-importer:use-webservice-demo-credentials
```

### Benutzerdefinierter CSV-Import
```bash
bin/console topdata:demo-data-importer:import-products-csv --file=products.csv --number=4 --name=2 --brand=3