# recette-tp

composer install
changer DATABASE_URL dans env
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
symfony server:start
