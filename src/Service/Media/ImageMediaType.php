<?php

namespace App\Service\Media;

/**
 * Dossiers sous public/uploads/, séparés par type de données.
 */
final class ImageMediaType
{
    public const BOOK = 'books';
    public const MAGAZINE_COLLECTION = 'magazines/collections';
    public const MAGAZINE_NUMERO = 'magazines/numeros';
    public const DVD = 'dvd';
    public const DVD_USER = 'dvd/user';
    public const MUSIQUE = 'musique';
    public const MUSIQUE_USER = 'musique/user';
    public const GAME = 'games';
    public const GAME_GALLERY = 'game';
    public const GAME_USER = 'game/user';
    public const BRICK = 'brick';
    public const AVATAR = 'avatars';

    /** @var string[] */
    public const ALL = [
        self::BOOK,
        self::MAGAZINE_COLLECTION,
        self::MAGAZINE_NUMERO,
        self::DVD,
        self::DVD_USER,
        self::MUSIQUE,
        self::MUSIQUE_USER,
        self::GAME,
        self::GAME_GALLERY,
        self::GAME_USER,
        self::BRICK,
        self::AVATAR,
    ];

    private function __construct()
    {
    }
}
