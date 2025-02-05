#!/bin/bash

echo "Déploiement en cours..."

# Se rendre dans le dossier du projet
cd /home/eccemiz/testapi

# Installer les dépendances Composer
composer install --no-dev --optimize-autoloader

echo "Déploiement terminé !"
