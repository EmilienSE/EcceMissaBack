#!/bin/bash
php /home/eccemiz/api/bin/console messenger:consume async --limit=10 --no-debug
