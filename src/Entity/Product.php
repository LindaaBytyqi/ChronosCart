<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private ?string $sku = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $basePrice = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?int $initialStock = null;

    #[ORM\Column(length: 100)]
    private ?string $status = null;

    #[ORM\OneToMany(targetEntity: PriceLog::class, mappedBy: 'product')]
    private Collection $priceLogs;

    /**
     * @var Collection<int, OrderItems>
     */
    #[ORM\OneToMany(targetEntity: OrderItems::class, mappedBy: 'product')]
    private Collection $orderItems;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageName = null;

    public function __construct()
    {
        $this->priceLogs = new ArrayCollection();
        $this->orderItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(string $sku): static
    {
        $this->sku = $sku;

        return $this;
    }

    public function getBasePrice(): ?string
    {
        return $this->basePrice;
    }

    public function setBasePrice(string $basePrice): static
    {
        $this->basePrice = $basePrice;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getInitialStock(): ?int
    {
        return $this->initialStock;
    }

    public function setInitialStock(int $initialStock): static
    {
        $this->initialStock = $initialStock;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
    * @return Collection<int, PriceLog>
    */

public function getPriceLogs(): Collection
{
    return $this->priceLogs;
}

public function addPriceLog(PriceLog $priceLog): static
{
    if (!$this->priceLogs->contains($priceLog)) {
        $this->priceLogs->add($priceLog);
        $priceLog->setProduct($this);
    }

    return $this;
}

public function removePriceLog(PriceLog $priceLog): static
{
    $this->priceLogs->removeElement($priceLog);
    return $this;
}

/**
 * @return Collection<int, OrderItems>
 */
public function getOrderItems(): Collection
{
    return $this->orderItems;
}

public function addOrderItem(OrderItems $orderItem): static
{
    if (!$this->orderItems->contains($orderItem)) {
        $this->orderItems->add($orderItem);
        $orderItem->setProduct($this);
    }

    return $this;
}

public function removeOrderItem(OrderItems $orderItem): static
{
    if ($this->orderItems->removeElement($orderItem)) {
        // set the owning side to null (unless already changed)
        if ($orderItem->getProduct() === $this) {
            $orderItem->setProduct(null);
        }
    }

    return $this;
}

public function getImageName(): ?string
{
    return $this->imageName;
}

public function setImageName(?string $imageName): static
{
    $this->imageName = $imageName;

    return $this;
}

}
