# Projet : ecuriesdugrandvignoble.com

## Hébergement

- **VPS** : Hetzner
- **Panel** : cPanel
- **Username cPanel** : `ecuriesdugrandvi`
- **Deploy path** : `/home/ecuriesdugrandvi/public_html/`
- **Déploiement** : via `.cpanel.yml` (Git Version Control dans cPanel)

## Stack technique

- HTML5 / CSS3 / JavaScript vanilla (pas de framework)
- Pas de build step
- Déploiement par push Git → cPanel pull + exécution `.cpanel.yml`

## Conventions de code

- **Mobile-first** : écrire le CSS pour mobile d'abord, puis `@media (min-width: ...)` pour desktop
- **Images** : format WebP en priorité, JPG en fallback si nécessaire
- **SVG** : inline dans le HTML quand c'est une icône (pour pouvoir styliser via CSS)
- **Pas de hotlink images** : toutes les images doivent être hébergées sur le domaine (`assets/`)
- **Alt text obligatoire** sur toutes les images (SEO + accessibilité)
- **Chemins relatifs uniquement** : `css/style.css`, jamais `/css/style.css`. Sinon le site casse en `file://` ou en sous-dossier.

## Cache-busting (IMPORTANT)

Le `.htaccess` met en cache CSS/JS pendant **1 mois**. Sans cache-busting, les visiteurs récurrents continuent de voir l'ancienne version après une modif.

**Règle** : à chaque modification de `css/style.css` ou `js/main.js`, il faut bumper le query string `?v=AAAAMMJJx` dans **toutes** les pages HTML qui les référencent.

Format : `?v=AAAAMMJJx`
- `AAAAMMJJ` = date du jour
- `x` = lettre incrémentale (`a`, `b`, `c`...) pour les modifs multiples le même jour

Exemple :
```html
<link rel="stylesheet" href="css/style.css?v=20260427a">
<script src="js/main.js?v=20260427a"></script>
```

Après modif le même jour :
```html
<link rel="stylesheet" href="css/style.css?v=20260427b">
```

## SEO

- **Title** unique et descriptif sur chaque page (50-60 caractères)
- **Meta description** unique sur chaque page (150-160 caractères)
- **Open Graph** complet (og:title, og:description, og:image, og:url, og:type, og:locale)
- **Schema.org** : ajouter du JSON-LD pour les pages clés (Organization, LocalBusiness, etc.)
- **sitemap.xml** : maintenir à jour avec toutes les pages
- **robots.txt** : pointe vers le sitemap
- **URLs propres** : `/contact` plutôt que `/contact.html` (gérer via `.htaccess` si besoin)

## Git

- **`main`** = branche de production (déclenche le déploiement cPanel)
- **Jamais de push direct sur `main`** : passer par une branche feature + merge
- **Branches feature** : `feature/nom-de-la-feature` ou `fix/nom-du-bug`
- **Commits** : messages clairs en français, format `verbe + objet` (ex: "ajouter page contact")

## Déploiement

1. Modifier sur la branche feature
2. Tester en local (ouvrir `index.html` directement)
3. Merger dans `main`
4. Push → cPanel détecte le push et exécute `.cpanel.yml`
5. Si modif CSS/JS : bumper le `?v=` AVANT le merge

## Structure

```
.
├── .cpanel.yml          # Tâches de déploiement
├── .htaccess            # Config Apache (HTTPS, cache, sécurité)
├── .gitignore
├── 404.html
├── index.html
├── robots.txt
├── sitemap.xml
├── assets/              # Images, fonts, favicons
├── css/
│   └── style.css
└── js/
    └── main.js
```
