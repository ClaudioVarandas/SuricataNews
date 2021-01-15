<p align="center">
    <img src="public/assets/logo.png" width="400" height="400" alt="SuricataNews logo">
</p>


# Suricata News Notifier

This project was created to be a new/informations aggregator and notifier, and is based in two main components :

- Commands
    - CLI scripts used to fetch or scrap data
        - Covid19 Portugal
        - Weather
        - News

- Notifications
    - Social Bot's
        - Telegram notifications
            - [x] Channel SuricataNews https://t.me/SuricataNews

### Roadmap

##### Covid19 Portugal data (DONE)

- Features
    - [x] Full database update
    - [x] Get daily numbers
    - [x] Get daily county numbers

- Data sources (VOSTPT and DSSG)
    - https://covid19-api.vost.pt/
    - https://github.com/dssg-pt/covid19pt-data

##### Weather (WIP)

- Features
    - [x] Current conditions
    - [x] Daily forecasts (next 5 days)
    - [x] Severe weather (alerts)

- Data source
    - https://www.weatherbit.io/api

##### News (TODO)

- Features
    - [] Recent news

- Data source
    - ???


### Requirements

- Apache 2.4
- PHP >=7.3
- Docker backend:
    - Mariadb 10.5

Start docker in the root of the project:

```
docker-compose up -d 
```

### Installation

- Create database schema
```
 php artisan migrate
```

- Running the task scheduler, add to your server cron jobs
```
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

- Populate the database
```
php artisan covid19pt:full-update-daily
php artisan covid19pt:full-update-counties
```
