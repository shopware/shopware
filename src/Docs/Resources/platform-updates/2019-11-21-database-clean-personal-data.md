[titleEn]: <>(Cleaning personal data)

```bash
>  bin/console database:clean-personal-data
```

Execute this command to clean the personal data of guests without orders and canceled carts.

Required is one of the two following arguments:
    
    1. guests
    2. carts
    
to remove only the guests or only the carts.

Otherwise you can choose the option:

    --all
    
to remove both.

The option 

    --days
    
can be used to set how old the data should be.

If the data exists same old or older, than it will be removed.

#### Example

Execute `bin/console database:clean-personal-data --all --days 5` to remove all guests without orders and all canceled carts which are same old or older than 5 days.
