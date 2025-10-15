<?php

namespace App\Entity;

use App\Repository\VideoGameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: VideoGameRepository::class)]
class VideoGame
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['videogame:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le nom du jeu est requis")]
    #[Assert\Length(max: 255, maxMessage: "Le nom du jeu ne peut pas dépasser les 255 caractères.")]
    #[Groups(['videogame:read', 'videogame:write'])]
    private ?string $title = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: "La date de sorti du jeu est requis")]
    #[Assert\Type(
        type: \DateTimeInterface::class,
        message: "La date de sortie doit être une date valide (format YYYY-MM-DD)."
    )]
    #[Groups(['videogame:read', 'videogame:write'])]
    private ?\DateTime $releaseDate = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "La description du jeu est requis")]
    #[Groups(['videogame:read', 'videogame:write'])]
    private ?string $description = null;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'videoGames')]
    #[Assert\NotBlank(message: "Au moins une catégorie est requise")]
    #[Groups(['videogame:read', 'videogame:write'])]
    private Collection $categories;

    #[ORM\ManyToOne(inversedBy: 'videoGames')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank(message: "L'éditeur du jeu est requis")]
    #[Groups(['videogame:read', 'videogame:write'])]
    private ?Editor $editor = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverImage = null;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getReleaseDate(): ?\DateTime
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(\DateTime $releaseDate): static
    {
        $this->releaseDate = $releaseDate;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        $this->categories->removeElement($category);

        return $this;
    }

    public function getEditor(): ?Editor
    {
        return $this->editor;
    }

    public function setEditor(?Editor $editor): static
    {
        $this->editor = $editor;

        return $this;
    }

    public function getCoverImage(): ?string
    {
        return $this->coverImage;
    }

    public function setCoverImage(?string $coverImage): static
    {
        $this->coverImage = $coverImage;

        return $this;
    }
}
