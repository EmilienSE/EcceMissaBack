for i in {1..60}  # Exécuter 60 fois pour chaque minute de l'heure
do
    /usr/local/php8.2/bin/php bin/console messenger:consume async --limit=10 --no-debug
    sleep 60  # Attendre 60 secondes avant de recommencer
done