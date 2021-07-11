# Bitclout Creator Coin

This package allows to follow the bonding curve of creator coins of BitClout project

## Installation

To install it just use composer

```sh
composer require muvon/bitclout-creator-coin
```

## Usage

### First initialiization

1. You can create brand new creator coin just by passing reward basis points before you go to buy and sell

    ```php
    use Muvon\Bitclout\CreatorCoin;
    $Coin = CreatorCoin::create(0); // pass reward basis points
    ```

2. You can initialize creator coin by locked amount and coins sold

    ```php
    use Muvon\Bitclout\CreatorCoin;
    $Coin = CreatorCoin::create(0); // pass reward basis points
    // Watermark is optional
    $Coin->init(1000, 1000, 0); // locked, supply and watermark in nanos
    ```

### Emulate buys

One you created object you can buy and sell using buy and sell methods

Lets buy for 1 $CLOUT

```php
$Coin->buy(1 * 10 ** 9);

// Check rate, locked and supply
var_dump($Coin->getLocked());
var_dump($Coin->getSupply());
var_dump($Coin->getRate());

// Check info about minting of last buy
var_dump($Coin->getLastBuy());
```

Sell is easy as buy just pass creator coins in nanos

```php
$Coin->sell(1 * 10 ** 9);
```

### Strategies

BitClout fixed some issues and fully changed creator coin stretegies after initial launch. To control emulation of it you need to pass strategy byself or set block height by method.

```php
// Latest strategy when founder get reward in $CLOUT
$Coin->setStrategy('reward');

// salamon bug fix strategy when founder always get reward in coins
$Coin->setStrategy('minting');

// Watermark is a initial strategy of BitClout blockchain, once supply reached no more reward minted for creator
$Coin->setStrategy('watermark');

// Or just set it with block height
$Coin->setStrategyByHeight(1000);
```

## Tests

- Buy new creator
- Sell creator coin
- Watermark strategy
- Init locked supply
