<p align="center">
    <img src="public/assets/logo.png" width="400" height="400" alt="SuricataNews logo">
</p>


# Suricata News BOT

This project was created to be a news/data aggregator and/or notifier. 
There are available commands to grab data store/cache it and trigger notifications. Probably in a near future i will create api endpoints to provide the data to other tools or apps.

### Features

Commands available:
- CLI scripts fetch or scrap data
    - Covid19 Portugal
        - [x] Fetch daily numbers
        - [x] Fetch daily county numbers
        - [x] Full database update
    - Weather IPMA warnings
        - [x] Fetch IPMA warnings

Notification channels:
- Telegram notifications
  - Channel SuricataNews https://t.me/SuricataNews
    

### Requirements

- Apache 2.4
- PHP >=7.3
- Docker backend:
    - Mariadb 10.5
    - Redis 6

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

### Commands available

```sh
 covid19pt:county-update         
 covid19pt:daily-update          
 covid19pt:full-update-counties  
 covid19pt:full-update-daily 
  
 weather:ipma:fetch-warnings
```

#### Data sources

Covid19 data Portugal (VOSTPT and DSSG):
- https://covid19-api.vost.pt/
- https://github.com/dssg-pt/covid19pt-data

IPMA:
- https://api.ipma.pt
