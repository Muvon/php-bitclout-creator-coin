<?php

use Muvon\Bitclout\CreatorCoin;

class CreatorCoinTest extends \PHPUnit\Framework\TestCase {
  public function testBuyNewCreator() {
    $Coin = CreatorCoin::create(0);
    $Coin->buy(1000);
    $last_buy = $Coin->getLastBuy();
    $this->assertEquals(9993, $Coin->getRate());
    $this->assertEquals(999, $Coin->getLocked());
    $this->assertEquals(99966681, $Coin->getSupply());
    $this->assertEquals(99966681, $last_buy['minted']);
    $this->assertEquals(0, $last_buy['reward']['coin']);
    $this->assertEquals(0, $last_buy['reward']['amount']);

    $Coin->buy(1000);
    $last_buy = $Coin->getLastBuy();
    $this->assertEquals(15863, $Coin->getRate());
    $this->assertEquals(1998, $Coin->getLocked());
    $this->assertEquals(125950122, $Coin->getSupply());
    $this->assertEquals(25983441, $last_buy['minted']);
    $this->assertEquals(0, $last_buy['reward']['coin']);
    $this->assertEquals(0, $last_buy['reward']['amount']);

    $Coin->setReward(1000);
    $Coin->setIsCreator(false);
    $Coin->buy(1000);
    $last_buy = $Coin->getLastBuy();
    $this->assertEquals(20326, $Coin->getRate());
    $this->assertEquals(2898, $Coin->getLocked());
    $this->assertEquals(142571554, $Coin->getSupply());
    $this->assertEquals(16621432, $last_buy['minted']);
    $this->assertEquals(0, $last_buy['reward']['coin']);
    $this->assertEquals(99, $last_buy['reward']['amount']);
  }

  public function testSellCreatorCoin() {
    $Coin = CreatorCoin::create(0);
    $Coin->buy(1000);

    $Coin->sell(50000);
    $this->assertEquals(998, $Coin->getLocked());
    $this->assertEquals(99916681, $Coin->getSupply());

    $Coin->sell(99916681);
    $this->assertEquals(0, $Coin->getLocked());
    $this->assertEquals(0, $Coin->getSupply());

    $this->expectException(InvalidArgumentException::class);
    $Coin->sell(100000000);
  }

  // Test previous strategies used by network before
  public function testWatermarkStrategy() {
    $Coin = CreatorCoin::create(1000);
    $Coin->setIsCreator(false);
    $Coin->setStrategy('watermark');

    $Coin->buy(1000000);
    $last_buy = $Coin->getLastBuy();
    $this->assertEquals(0, $last_buy['reward']['amount']);
    $this->assertEquals(99996669, $last_buy['reward']['coin']);
    $this->assertEquals($Coin->getSupply(), $Coin->getWatermark());

    $Coin->sell(1000000);
    $Coin->buy(2000);
    $last_buy = $Coin->getLastBuy();
    $this->assertEquals(0, $last_buy['reward']['amount']);
    $this->assertEquals(0, $last_buy['reward']['coin']);

    $Coin->buy(5000);
    $last_buy = $Coin->getLastBuy();
    $this->assertEquals(0, $last_buy['reward']['amount']);
    $this->assertEquals(133204, $last_buy['reward']['coin']);
  }

  public function testMintingStrategy() {
    $Coin = CreatorCoin::create(1000);
    $Coin->setIsCreator(false);
    $Coin->setStrategy('minting');

    $Coin->buy(1000000);
    $last_buy = $Coin->getLastBuy();
    $this->assertEquals(0, $last_buy['reward']['amount']);
    $this->assertEquals(99996669, $last_buy['reward']['coin']);

    $Coin->sell(1000000);
    $Coin->buy(2000);
    $last_buy = $Coin->getLastBuy();
    $this->assertEquals(0, $last_buy['reward']['amount']);
    $this->assertEquals(66726, $last_buy['reward']['coin']);

    $Coin->buy(5000);
    $last_buy = $Coin->getLastBuy();
    $this->assertEquals(0, $last_buy['reward']['amount']);
    $this->assertEquals(166477, $last_buy['reward']['coin']);
  }

  public function testInitLockedSupply() {
    $Coin = CreatorCoin::create(0);
    $Coin->init(999900000, 9999664686);
    $this->assertEquals(99993352, $Coin->getRate());
    $this->assertEquals(999900000, $Coin->getLocked());
    $this->assertEquals(9999664686, $Coin->getSupply());
    $this->assertEquals($Coin->getSupply(), $Coin->getWatermark());

    $this->expectException(Exception::class);
    $Coin->init(1, 1);
  }
}