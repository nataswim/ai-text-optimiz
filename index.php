<?php
// Silence is golden
// Ce fichier empêche l'accès direct au dossier du plugin

// Redirection vers l'accueil du site
if (!defined('ABSPATH')) {
    header('Location: ' . get_home_url());
    exit;
}