<?php

/**
 * Classe che rappresenta un utente nel sistema.
 */
class EUser
{
    public $id;
    public $username;
    public $email;
    public $passwordHash;
    public $isAdmin;

    /**
     * Inizializza l'utente con i suoi dati di base.
     */
    public function __construct($id, $username, $email, $passwordHash, $isAdmin = false)
    {
        $this->id = $id;
        $this->username = $username;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->isAdmin = $isAdmin;
    }

    /**
     * Costruisce un'istanza di EUser a partire da una riga associativa del database.
     */
    public static function fromRow($row)
    {
        return new self(
            (int) $row['id'], 
            (string) $row['username'],
            (string) $row['email'], 
            (string) $row['password_hash'],
            (bool) ($row['is_admin'] ?? false)
        );
    }
}
