<?php
namespace Muvon\Bitclout;

use Exception;
use InvalidArgumentException;

class CreatorCoin {
  const TRADE_FEE = 1;
  const RESERVE_RATIO = 0.3333333;
  const SLOPE = 0.003;
  const NANOS_PER_UNIT = 1000000000;
  const THRESHOLD = 10;

  protected int $locked = 0;
  protected int $supply = 0;
  protected int $rate = 0;
  protected int $watermark = 0;
  protected bool $is_creator = true;

  // watermark | minting | reward
  // Strategy changed by block height:
  //   watermark - <= 15270
  //   minting - <= 21869
  //   reward - > 21869
  protected string $strategy = 'reward';

  // Last buy and sell info
  protected array $buy = [];
  protected array $sell = [];

  final protected function __construct(protected int $reward) {}

  /**
   * Create new creator coin object with given reward
   *
   * @param int $reward
   *   Reward basis points
   * @return static
   */
  public static function create(int $reward): static {
    return new static($reward);
  }

  /**
   * Initialize creator coin with base data available
   *
   * @param int $locked
   *   Locked amount in nanos
   * @param int $supply
   *   Total supply in $creator coins in nanos
   * @param int $watermark
   *   Optional watermark in case we use same strategy
   * @return static
   */
  public function init(int $locked, int $supply, int $watermark = 0): static {
    if ($this->supply > 0) {
      throw new Exception('Creator coin was inited already');
    }
    $this->locked = $locked;
    $this->supply = $supply;
    $this->watermark = max($supply, $watermark);
    $this->recalculateRate();
    return $this;
  }

  /**
   * Set current strategy for next buys/sells
   * Current default strategy is "reward"
   * @param string $strategy
   *   One of watermark, minting or reward
   * @return static
   */
  public function setStrategy(string $strategy): static {
    $this->strategy = $strategy;
    return $this;
  }


  /**
   * Set strategy according to transaction block height
   *
   * @param int $height
   * @return static
   */
  public function setStrategyByHeight(int $height): static {
    $this->strategy = match (true) {
      $height <= 15270 => 'watermark',
      $height > 15270 && $height <= 21869 => 'minting',
      $height > 21869 => 'reward',
    };

    return $this;
  }

  /**
   * Set current reward for next buys
   *
   * @param int $reward
   *   Reward basis points as int
   * @return static
   */
  public function setReward(int $reward): static {
    $this->reward = $reward;
    return $this;
  }

  public function setIsCreator(bool $is_creator): static  {
    $this->is_creator = $is_creator;
    return $this;
  }

  public function setWatermark(int $watermark): static {
    $this->watermark = max($this->supply, $watermark);
    return $this;
  }

  // https://github.com/bitclout/core/blob/d268ff4d11f98b65a0438d84b3e9a5397eaef84e/lib/block_view.go#L2039
  /**
   * Emulate buy creator coins of account
   *
   * @param int $amount
   *  Amount we buy in nanos of $clout
   * @return static
   */
  public function buy(int $amount, bool $preview = false): static {
    if ($this->reward === 10000 && !$this->is_creator && $this->strategy === 'reward') {
      throw new InvalidArgumentException('Incorrect reward basis points');
    }
    $trade_amount = $this->applyTradeFees($amount);
    if ($trade_amount === 0) {
      throw new Exception('Invalid amount after fees');
    }

    $reward_amount = 0;
    if ($this->strategy === 'reward' && !$this->is_creator) {
      $reward_amount = intval(($trade_amount * $this->reward) / (100 * 100));
    }

    $buy_amount = $trade_amount - $reward_amount;
    if ($this->locked === 0) {
      $minted = $this->calculatePolynomialMinting($buy_amount);
    } else {
      $minted = $this->calculateBancorMinting($buy_amount);
    }

    if (!$preview) {
      $this->locked += $buy_amount;
      $this->supply += $minted;
      $this->recalculateRate();
    }

    $reward = ['amount' => $reward_amount, 'coin' => 0];
    $watermark = max($this->supply, $this->watermark);
    if ($this->strategy === 'watermark' && !$this->is_creator && $watermark > $this->watermark) {
      $reward['coin'] = intval((($watermark - $this->watermark) * $this->reward) / (100 * 100));
    }
    if (!$preview) {
      $this->watermark = $watermark;
    }

    if ($this->strategy === 'minting' && !$this->is_creator) {
      $reward['coin'] = intval(($minted * $this->reward) / (100 * 100));
    }
    $received = $this->is_creator ? $minted : intval($minted - $reward['coin']);

    $this->buy = [
      'preview' => $preview,
      'amount' => $buy_amount,
      'minted' => $minted,
      'received' => $received,
      'reward' => $reward,
      'rate' => intval(($buy_amount / $minted) * static::NANOS_PER_UNIT),
    ];
    return $this;
  }

  /**
   * Emulate selling of amount of current holded creator tokens
   *
   * @param int $amount
   *   Amount of creator coins in nanos
   * @return static
   */
  public function sell(int $amount, bool $preview = false): static {
    $returned = $this->calculateReturned($amount);

    if (!$preview) {
      $this->locked -= $returned;
      $this->supply -= $amount;
      // Emulate zero holder
      // https://github.com/bitclout/core/blob/d268ff4d11f98b65a0438d84b3e9a5397eaef84e/lib/block_view.go#L5654
      if ($this->supply === 0) {
        $this->locked = 0;
      }
      $this->recalculateRate();
    }

    $credited = $this->applyTradeFees($returned);
    $this->sell = [
      'preview' => $preview,
      'amount' => $amount,
      'returned' => $credited,
      'rate' => intval(($credited / $amount) * static::NANOS_PER_UNIT),
    ];

    return $this;
  }

  // Internal used functions
  protected function calculatePolynomialMinting(int $amount): int {
    $delta_amount = $amount / static::NANOS_PER_UNIT;
    $delta_supply = $this->supply / static::NANOS_PER_UNIT;
    $minted = (
      ($delta_amount + static::SLOPE * static::RESERVE_RATIO * $delta_supply ** (1 / static::RESERVE_RATIO))
       /
      (static::SLOPE * static::RESERVE_RATIO)
    ) ** static::RESERVE_RATIO - $delta_supply;

    return intval($minted * static::NANOS_PER_UNIT);
  }

  protected function calculateBancorMinting(int $amount): int {
    $delta_amount = $amount / static::NANOS_PER_UNIT;
    $delta_supply = $this->supply / static::NANOS_PER_UNIT;
    $delta_locked = $this->locked / static::NANOS_PER_UNIT;
    $minted = $delta_supply * ((1 + $delta_amount / $delta_locked) ** static::RESERVE_RATIO - 1);
    return intval($minted * static::NANOS_PER_UNIT);
  }

  protected function calculateReturned(int $amount): int {
    if ($amount > $this->supply) {
      throw new InvalidArgumentException('Amount of coins is out of supply');
    }
    $delta_amount = $amount / static::NANOS_PER_UNIT;
    $delta_supply = $this->supply / static::NANOS_PER_UNIT;
    $delta_locked = $this->locked / static::NANOS_PER_UNIT;
    $returned = (($delta_locked * (1 - (1 - $delta_amount / $delta_supply) ** (1 / static::RESERVE_RATIO))) * static::NANOS_PER_UNIT);
    if ($returned >= $this->locked) {
      $returned = $this->locked;
    }
    return $returned;
  }

  protected function applyTradeFees(int $amount): int {
    return intval(($amount * (100 * 100 - static::TRADE_FEE)) / (100 * 100));
  }

  protected function recalculateRate(): void {
    $this->rate = $this->supply > 0  ? intval(($this->locked / $this->supply) * static::NANOS_PER_UNIT) : 0;
  }

  // Getters
  public function getLocked(): int {
    return $this->locked;
  }

  public function getSupply(): int {
    return $this->supply;
  }

  public function getRate(): int {
    return $this->rate;
  }

  public function getWatermark(): int {
    return $this->watermark;
  }

  public function getLastBuy(): array {
    return $this->buy;
  }

  public function getLastSell(): array {
    return $this->sell;
  }

  public function getStrategy(): string {
    return $this->strategy;
  }

  // This method is required to call with additional parameter
  // To follow code logic of salamon bug fixing
  public function adjustSellAmount(int $amount, int $held): int {
    if ($held < $amount) {
      throw new Exception('Invalid held amount. Trying to sell ' . $amount . ' out of ' . $held);
    }

    // Salamon bug fixed after watermark startegy
    if ($this->strategy !== 'watermark' && ($held - $amount) < static::THRESHOLD) {
      $amount = $held;
    }

    return $amount;
  }
}