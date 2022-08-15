# Архиватор пользователей, сообществ, постов, лайков и комментариев с сайта vk.com через API ВКонтакте
[![Version](http://poser.pugx.org/kostyan-org/vk-archiver/version)](https://packagist.org/packages/kostyan-org/vk-archiver)
[![Total Downloads](http://poser.pugx.org/kostyan-org/vk-archiver/downloads)](https://packagist.org/packages/kostyan-org/vk-archiver)
[![License](http://poser.pugx.org/kostyan-org/vk-archiver/license)](https://packagist.org/packages/kostyan-org/vk-archiver)
[![PHP Version Require](http://poser.pugx.org/kostyan-org/vk-archiver/require/php)](https://packagist.org/packages/kostyan-org/vk-archiver)

![Image](https://github.com/kostyan-org/vk-archiver/raw/gh-pages/vk-archiver.PNG)

[Home page](https://kostyan-org.github.io/vk-archiver)

## Установка:

    composer create-project kostyan-org/vk-archiver
    cd .\vk-archiver\

редактируем файл **.env**

    VK_API_TOKEN=полученный токен от вк
    VK_API_USER=айди юзера токена
    DATABASE_URL="mysql://root:@127.0.0.1:3306/название новой БД?charset=utf8mb4"

создаем новую БД

    php bin/console doctrine:database:create
    php bin/console doctrine:migrations:migrate

## Работа
список команд

    php bin/console app:

пример загрузки 3-х последних постов с лайками и комментариями из группы vk

    php bin/console app:download vk --likes --comments --limit 3

список готовых методов статистики

    php bin/console app:stat --methods

пример просмотра статистики группы - vk

    php bin/console app:stat --method statwall --source vk