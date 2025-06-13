<?php

namespace App\Entity;

use App\Repository\ChildRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChildRepository::class)]
class Child
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    private ?string $lastname = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $birthday = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $picture = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $allergy = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $health_condition = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $signing_date = null;

    #[ORM\ManyToOne(inversedBy: 'children')]
    private ?Team $team = null;

    /**
     * @var Collection<int, Presence>
     */
    #[ORM\OneToMany(targetEntity: Presence::class, mappedBy: 'child')]
    private Collection $presences;

    /**
     * @var Collection<int, ChildUser>
     */
    #[ORM\OneToMany(targetEntity: ChildUser::class, mappedBy: 'child', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $childUsers;

    public function __construct()
    {
        $this->presences = new ArrayCollection();
        $this->childUsers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getBirthday(): ?\DateTime
    {
        return $this->birthday;
    }

    public function setBirthday(\DateTime $birthday): static
    {
        $this->birthday = $birthday;

        return $this;
    }

    public function getPicture(): ?string
    {
        return $this->picture;
    }

    public function setPicture(?string $picture): static
    {
        $this->picture = $picture;

        return $this;
    }

    public function getAllergy(): ?string
    {
        return $this->allergy;
    }

    public function setAllergy(?string $allergy): static
    {
        $this->allergy = $allergy;

        return $this;
    }

    public function getHealthCondition(): ?string
    {
        return $this->health_condition;
    }

    public function setHealthCondition(?string $health_condition): static
    {
        $this->health_condition = $health_condition;

        return $this;
    }

    public function getSigningDate(): ?\DateTime
    {
        return $this->signing_date;
    }

    public function setSigningDate(\DateTime $signing_date): static
    {
        $this->signing_date = $signing_date;

        return $this;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;

        return $this;
    }

    /**
     * @return Collection<int, Presence>
     */
    public function getPresences(): Collection
    {
        return $this->presences;
    }

    public function addPresence(Presence $presence): static
    {
        if (!$this->presences->contains($presence)) {
            $this->presences->add($presence);
            $presence->setChild($this);
        }

        return $this;
    }

    public function removePresence(Presence $presence): static
    {
        if ($this->presences->removeElement($presence)) {
            // set the owning side to null (unless already changed)
            if ($presence->getChild() === $this) {
                $presence->setChild(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ChildUser>
     */
    public function getChildUsers(): Collection
    {
        return $this->childUsers;
    }

    public function addChildUser(ChildUser $childUser): static
    {
        if (!$this->childUsers->contains($childUser)) {
            $this->childUsers->add($childUser);
            $childUser->setChild($this);
        }

        return $this;
    }

    public function removeChildUser(ChildUser $childUser): static
    {
        if ($this->childUsers->removeElement($childUser)) {
            // set the owning side to null (unless already changed)
            if ($childUser->getChild() === $this) {
                $childUser->setChild(null);
            }
        }

        return $this;
    }
}
