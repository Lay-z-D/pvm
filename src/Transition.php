<?php

namespace Formapro\Pvm;

use function Formapro\Values\get_value;
use function Formapro\Values\set_value;
use Formapro\Values\ValuesTrait;

class Transition
{
  const SCHEMA = 'http://pvm.forma-pro.com/schemas/Transition.json';

  use ValuesTrait {
    setValue as public;
    getValue as public;
  }
  use CreateTrait;

  /**
   * @var Process
   */
  private $_process;

  public function __construct()
  {
    $this->setWeight(1);
    $this->setAsync(false);
    $this->setActive(true);
  }

  public function setDatabaseId(mixed $databaseId): void
  {
      set_value($this, 'database_id', $databaseId);
  }

  public function getDatabaseId(): mixed
  {
      return get_value($this, 'database_id');
  }

  public function setId(string $id): void
  {
    set_value($this, 'id', $id);
  }

  public function getId(): string
  {
    return get_value($this, 'id');
  }

  public function getName(): ?string
  {
    return get_value($this, 'name');
  }

  public function setName(string $name = null): void
  {
    set_value($this, 'name', $name);
  }

  public function setProcess(Process $process): void
  {
    $this->_process = $process;
  }

  public function getProcess(): Process
  {
    return $this->_process;
  }

  public function getFrom(): ?Node
  {
    if ($id = get_value($this, 'from')) {
      return $this->_process->getNode($id);
    }

    return null;
  }

  public function setFrom(?Node $node): void
  {
    set_value($this, 'from', $node->getId());
  }

  public function getTo(): ?Node
  {
    if ($id = get_value($this, 'to')) {
      return $this->_process->getNode($id);
    }

    return null;
  }

  public function setTo(?Node $node): void
  {
    set_value($this, 'to', $node->getId());
  }

  public function getWeight(): int
  {
    return get_value($this, 'weight');
  }

  public function setWeight(?int $weight): void
  {
    set_value($this, 'weight', $weight);
  }

  public function isAsync(): bool
  {
    return get_value($this, 'async');
  }

  public function setAsync(bool $async): void
  {
    set_value($this, 'async', $async);
  }

  public function isActive(): bool
  {
    return get_value($this, 'active');
  }

  public function setActive(bool $active): void
  {
    set_value($this, 'active', $active);
  }
}
