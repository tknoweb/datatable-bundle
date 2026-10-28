# tknoweb/datatable-bundle

Bundle Symfony maison (`Tknoweb\DatatableBundle`) générant des datatables serveur : colonnes typées,
tri/pagination persistés en session, filtres via formulaire Symfony, actions par ligne, rendu Twig +
Turbo Frame + Stimulus.

Le bundle maintient **deux documentations distinctes**, volontairement non fusionnées :

| Fichier | Public | Contenu |
|---|---|---|
| `CLAUDE.md` (ce fichier) | instances Claude | conventions d'implémentation, contraintes de l'API, signatures exactes, pièges |
| [README.md](README.md) | développeurs | installation, intégration pas à pas, référence des paramètres |

**Les deux doivent rester synchronisées avec le code** — voir « Maintenir la documentation à jour »
en fin de fichier.

Ce fichier étant livré dans le package, un projet consommateur l'importe dans son propre
`CLAUDE.md` :

```md
## Datatables
Ce projet utilise le bundle tknoweb/datatable-bundle.
@vendor/tknoweb/datatable-bundle/CLAUDE.md
```

Sections « Installation » à « Pièges connus » : utilisation du bundle dans un projet.
Section « Architecture interne » et suivantes : développement du bundle lui-même.

---

## Installation dans un projet

Procédure détaillée : [README.md](README.md).

```bash
composer require tknoweb/datatable-bundle
```

Enregistrer le bundle dans `config/bundles.php` (pas de recipe Flex) :

```php
Tknoweb\DatatableBundle\TknowebDatatableBundle::class => ['all' => true],
```

Prérequis côté application : PHP >= 8.1, Symfony >= 6.4, Doctrine ORM, **session activée**
(tri/limite/filtres y sont stockés), Twig. Turbo et Stimulus sont optionnels mais nécessaires pour la
pagination sans rechargement.

### Assets

- **AssetMapper** : le dossier `assets` du bundle est auto-déclaré sous `@tknoweb/datatable-bundle`
  (voir `prependExtension()`), le contrôleur Stimulus doit être exposé sous le nom `datatable`.
- **Webpack Encore** : `assets/package.json` déclare le contrôleur `datatable` (`symfony.controllers`)
  avec autoimport du CSS.
- Le nom du contrôleur Stimulus attendu par les templates est **`datatable`**
  (`stimulus_controller('datatable', ...)`).
- Le CSS (`assets/styles/datatable.css`) doit être importé par l'application si Encore ne le fait
  pas ; il stylise `.datatable-*` et l'overlay de chargement du Turbo Frame. Il est écrit à plat,
  sans imbrication ni étape de compilation : le modifier directement, il n'y a pas de source SCSS.

### Configuration

Clé de configuration : **`tknoweb_datatable`** (`config/packages/tknoweb_datatable.yaml`).

```yaml
tknoweb_datatable:
  column:
    route: ~                 # route par défaut au clic sur une cellule
    route_parameters: ~
    head_css_class: ~        # classe sur les <th>
    cell_css_class: ~        # classe sur les <td>
    bool_text_true: 'Oui'
    bool_text_false: 'Non'
    date_time_format: 'd/m/Y H:i:s'
    date_format: 'd/m/Y'
  action_column:
    template_style: inline   # inline | grouped (dropdown Bootstrap)
  action:
    css_class: ~             # classe par défaut des liens d'action
  datatable:
    css_class: ~             # classe ajoutée sur <table>
    default_limit_max: 25          # -1 pour afficher tous les résultats
    possible_limit_max: [10, 25, 50]   # valeurs > 0, ou -1 pour « tout afficher »
```

Ces valeurs deviennent les paramètres conteneur `datatable.column`, `datatable.datatable`,
`datatable.action`, `datatable.action_column`, lus comme défauts par chaque `OptionsResolver`.

---

## Convention d'implémentation — à suivre pour tout nouveau code

C'est la structure recommandée par le bundle.

### Choisir le pattern du projet — première chose à vérifier

Deux organisations coexistent. **Regarder ce que fait déjà le projet avant d'écrire quoi que ce
soit** (`ls src/Datatable/` et `grep -rl DatatableFactory src/Controller/`) :

| Le projet a… | Alors |
|---|---|
| un dossier `src/Datatable/` | **pattern cible** : suivre intégralement la recette ci-dessous |
| aucune datatable (projet neuf) | **pattern cible** : suivre intégralement la recette ci-dessous |
| des datatables construites dans les contrôleurs | **conserver l'organisation existante** : déclarer la datatable dans le contrôleur, comme ses voisines |

Dans le troisième cas, tout le reste de cette recette reste valable — ordre d'appel, conventions de
nommage, alias `x`, structure des filtres, templates : seul l'emplacement du code change. Ne pas
introduire `src/Datatable/` dans un projet qui n'en a pas : l'homogénéité au sein d'un projet prime.

Dans tous les cas, reprendre les conventions locales déjà en place (alias, nommage des routes, thème
de formulaire) — elles priment sur les exemples ci-dessous.

### Arborescence

```
src/Datatable/<Zone>/<Entite>Datatable.php        déclaration de la datatable
src/Form/<Zone>/Filter/<Entite>Filter.php         formulaire de filtre
src/Controller/<Zone>/<Entite>Controller.php      appelle la classe Datatable, rien de plus
templates/<zone>/<entite>/index.html.twig         page de liste
templates/<zone>/<entite>/datatable/*.html.twig   templates de colonnes
templates/bundles/TknowebDatatableBundle/         surcharges globales du bundle
```

`<Zone>` correspond au découpage applicatif existant (`Admin`, `Back`, `Api`…). Si le
projet n'en a pas, omettre ce niveau.

### 1. La classe Datatable

Une classe autonome par liste, suffixée `Datatable`, autowirée, sans classe de base. Elle expose une
méthode `generate()` qui retourne l'objet `Datatable` prêt à l'emploi :

```php
namespace App\Datatable\Admin;

class CityDatatable
{
    public function __construct(
        private readonly DatatableFactory $datatableFactory,
        private readonly RequestStack     $requestStack,
        private readonly CityRepository   $cityRepository
    )
    {
    }

    public function generate(): Datatable
    {
        return $this->datatableFactory
            ->createNamed('city-datatable')
            ->setFilterForm(CityFilter::class)
            ->addColumn('name', TextColumn::class)
            ->addColumn('country', TextColumn::class, ['order_field' => 'country.name'])
            ->addColumn('actions', ActionColumn::class)
            ->addAction('edit')
            ->addAction('show')
            ->addAction('delete')
            ->setDataSource(DoctrineDataSource::class, [
                'query_builder' => $this->cityRepository
                    ->createQueryBuilder('x')
                    ->addSelect('country')
                    ->leftJoin('x.country', 'country'),
            ])
            ->addOrderBy('name', 'asc')
            ->handleRequest($this->requestStack->getCurrentRequest());
    }
}
```

Règles :

- **Ordre d'appel canonique** : `createNamed` → `setFilterForm` → `addColumn`* → `addAction`* →
  `setDataSource` → `addOrderBy` → `handleRequest`. S'y tenir pour que toutes les datatables se
  lisent pareil.
- **Toujours `createNamed()`**, jamais `create()`. Nom au format **`<entite-en-kebab-case>-datatable`** :
  `city-datatable`, `client-payment-datatable`, `purchase-order-datatable`.
- **Méthode `generate()`**. Si une même entité a plusieurs listes distinctes (vues, exports), ajouter
  d'autres méthodes explicites (`generateForExport()`) plutôt que des paramètres booléens.
- **Injecter le repository concret** dans le constructeur (`CityRepository`) : pas d'`EntityManager`
  ni de méthode `getRepository()` intermédiaire.
- N'injecter que ce dont la datatable a besoin. Un service métier supplémentaire (`Security`, un
  manager) s'ajoute simplement au constructeur.
- Pas de classe abstraite commune imposée. Si un projet en a déjà une, la suivre ; ne pas en créer
  une pour une poignée de datatables.
- **Alias racine `x`** dans le QueryBuilder, alias de jointure nommés d'après la relation
  (`->leftJoin('x.country', 'country')`). Le `filter_query` des filtres s'appuie sur ces alias :
  changer l'alias racine casse les filtres.
- Ne pas répéter `'label' => …` quand le libellé par défaut (traduction du nom de colonne) suffit.
- `handleRequest()` en dernier, systématiquement.

### 2. Le contrôleur

Il injecte la classe Datatable dans l'action et ne contient **aucune** logique de datatable :

```php
#[Route('/', name: '_index')]
public function index(CityDatatable $cityDatatable): Response
{
    $datatable = $cityDatatable->generate();

    return $this->render('admin/city/index.html.twig', [
        'datatable' => $datatable,
        'filterForm' => $datatable->getFilterForm(),
    ]);
}
```

Passer `filterForm` à la vue dès qu'un formulaire de filtre existe : c'est lui qui est rendu dans le
template, `renderDatatable()` ne s'en charge pas.

### 3. Le formulaire de filtre

Dans `src/Form/<Zone>/Filter/`, suffixé `Filter`, étendant `AbstractBaseFilter` :

```php
namespace App\Form\Admin\Filter;

class CityFilter extends AbstractBaseFilter
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('name', TextFilter::class, [
                'filter_query' => function (QueryBuilder $qb, string $filterValue) {
                    return $qb->andWhere('x.name LIKE :name')
                        ->setParameter('name', "%$filterValue%");
                }
            ])
            ->add('country', EntityFilter::class, [
                'class' => Country::class,
                'filter_query' => function (QueryBuilder $qb, $filterValue) {
                    return $qb->andWhere('x.country = :country')
                        ->setParameter('country', $filterValue);
                }
            ])
            ->add('archived', SoftDeletableFilter::class);
    }
}
```

Règles :

- `parent::buildForm()` en **première** instruction (boutons `filterClear`/`filterSave` + listener de
  session).
- Un `filter_query` par champ, qui retourne `$qb` par convention de lecture — mais c'est bien la
  mutation du QueryBuilder qui est prise en compte, la valeur de retour est ignorée par le bundle.
- Les alias utilisés doivent exister dans le `query_builder` de la classe Datatable.
- Un filtre récurrent dans le projet (archivage, énumération…) se factorise en type dédié placé dans
  le même dossier (`SoftDeletableFilter`, `EnumFilter`) et se réutilise en une ligne.

### 4. Les templates

```twig
{# templates/admin/city/index.html.twig #}
{% extends 'admin/layout/list.html.twig' %}
{% form_theme filterForm 'admin/form/form_theme.html.twig' %}

{% block list_filter_form_fields %}
    <div class="col-md-3">{{ form_row(filterForm.name) }}</div>
    <div class="col-md-3">{{ form_row(filterForm.country) }}</div>
    <div class="col-md-3">{{ form_row(filterForm.archived) }}</div>
{% endblock %}

{% block list_content %}
    {{ renderDatatable(datatable) }}
{% endblock %}
```

- Le layout de liste du projet fournit les blocs (`list_filter_form_fields`, `list_content`) et le
  `form_start`/`form_end` du filtre : ne pas les réécrire par page.
- Les templates de colonnes vont dans `templates/<zone>/<entite>/datatable/<colonne>.html.twig`.
- Le rendu d'une action se personnalise **globalement** via
  `templates/bundles/TknowebDatatableBundle/action/_<nom>.html.twig` — c'est ce qui permet d'écrire
  `->addAction('edit')` sans aucune option. Vérifier ce dossier avant de surcharger une action au cas
  par cas dans la classe Datatable.

---

## Contraintes de l'API

**Ordre d'appel imposé** (chaque contrainte lève une `\Exception`) :

- `addAction()` exige qu'une `ActionColumn` ait déjà été ajoutée ; l'action est rattachée à la
  **dernière** `ActionColumn` déclarée.
- `addOrderBy()` exige que la colonne existe déjà **et qu'elle soit triable** — donc ni une
  `ActionColumn`, ni une colonne déclarée `'order_field' => false`, ni une `CustomColumn` sans
  `order_field` explicite.
- `setDataSource()` doit précéder `handleRequest()`.
- `setFilterForm()` doit précéder `handleRequest()` pour que les filtres soient appliqués.
- `handleRequest()` ne peut être appelé **qu'une seule fois**.
- `getResults()` / `getFilteredCount()` / `getTotalCount()` exigent `handleRequest()`.

### Nommage et session — point critique

`create()` utilise le nom par défaut `datatable`. Dans ce cas la clé de session est dérivée de la route
courante **amputée de son dernier segment** : route `app_user_index` → clé `datatable-app_user`. Les
filtres, le tri et la limite sont donc partagés par toute la famille de routes `app_user_*`.

`createNamed('users')` utilise `users` tel quel comme clé de session **et** comme préfixe des
paramètres de requête. **Deux datatables sur une même page imposent `createNamed()` avec des noms
distincts**, sinon elles partagent session et paramètres d'URL.

Paramètres de requête (préfixés par le nom de session) :
`<nom>[sort][<colonne>]=asc|desc`, `<nom>[offset]=N`, `<nom>[max]=N`.
Paramètres **non préfixés** (globaux à la page) : `resetPagination=1`, `resetFilters=1`,
`forceFilters[...]` (force des valeurs de filtre et écrase la session).

`addOrderBy()` définit le tri **par défaut** : il est écrasé par le tri en session ou en requête.

Ces paramètres sont validés et **silencieusement ignorés s'ils sont invalides**, y compris lorsqu'ils
proviennent de la session : une colonne inconnue ou retirée depuis, une direction autre que `asc` ou
`desc` (comparaison insensible à la casse), une limite absente de `possible_limit_max`. Aucune
exception n'est levée : le critère fautif disparaît, la limite retombe sur la session puis sur
`default_limit_max`.

### Limite « illimitée » et affichage du pied de page

La constante `Datatable::LIMIT_MAX_UNLIMITED` vaut **`-1`** et signifie « afficher tous les
résultats ». Elle est acceptée par `default_limit_max` et par les entrées de `possible_limit_max`,
en configuration globale comme dans les options passées à `createNamed()` ; toute autre valeur doit
être strictement positive.

```php
->createNamed('city-datatable', [
    'possible_limit_max' => [25, 50, Datatable::LIMIT_MAX_UNLIMITED],
    'default_limit_max'  => 25,
])
```

Comportements induits :

- La limite `-1` **force l'offset à 0** : il n'y a qu'une page.
- Le sélecteur de limite affiche l'entrée `-1` avec le libellé traduit `all` (`Tout` en français).
- Le pied de page s'auto-masque, par morceaux puis en entier :

| Méthode | Vrai quand | Effet |
|---|---|---|
| `getDisplayLimitSelector()` | plus d'une valeur dans `possible_limit_max` | affiche le sélecteur de limite |
| `getDisplayPagination()` | la limite courante n'est pas `-1` | affiche la pagination |
| `getDisplayFooter()` | l'un des deux ci-dessus | affiche le pied de page |

Donc `possible_limit_max: [-1]` supprime tout le pied de page, et `possible_limit_max: [25]`
n'affiche que la pagination. Le compteur d'éléments (`_datatable_footer_elements_shown`) reste
visible dès que le pied de page l'est.

Méthodes utilitaires associées : `isLimitMaxUnlimited()` et `getUnlimitedLimitMax()` (utilisée par
le template du sélecteur pour reconnaître l'entrée « tout afficher »).

> Une surcharge projet de `_datatable.html.twig` ou `_datatable_footer.html.twig` antérieure à cette
> évolution n'aura pas les conditions `datatable.displayFooter` / `displayLimitSelector` /
> `displayPagination` : le pied de page continuera de s'afficher, et la pagination plantera en mode
> illimité (division par `-1`). Vérifier `templates/bundles/TknowebDatatableBundle/` du projet.

### Template

```twig
{{ renderDatatable(datatable) }}
```

Pour la pagination/le tri en AJAX, envelopper dans le Turbo Frame fourni :

```twig
{{ include('@TknowebDatatable/_datatable_turbo_frame.html.twig', {'datatable': datatable}) }}
```

Le Turbo Frame n'est pas obligatoire. Les liens de pagination naviguent par leur `href` — Turbo s'en
charge dans un frame, le navigateur sinon ; le contrôleur Stimulus ne fait que le défilement en haut
de page. Le sélecteur de limite, lui, n'a pas de `href` : le contrôleur navigue par `Turbo.visit()`
dans un `.datatable-turbo-frame`, et par `window.location` en dehors.

Fonctions Twig exposées :

- `renderDatatable(datatable)` — rend le template complet (lève si `handleRequest()` n'a pas eu lieu).
- `datatableTurboFrameId(datatable)` → `datatable-frame-<nom>`.
- `currentUrlWithParameters({...})` — URL courante + query string reconstruite.
- `pathForCurrentControllerAction('editAction', {...})` — résout la route d'une autre action du
  contrôleur courant (scan de la RouteCollection, mis en cache par requête).
- `inverseColumnSort(sort)`.
- `filterCount(formView)` — nombre de filtres actifs d'un formulaire de filtre.

---

## Colonnes

| Classe | Usage | Triable |
|---|---|---|
| `TextColumn` | valeur scalaire ; gère les `BackedEnum` (méthode `trans($translator)` si présente, sinon `trans(value)`) | oui |
| `DateTimeColumn` | `\DateTime`/`\DateTimeImmutable` ; `null` → `''`, autre type → exception | oui |
| `BoolColumn` | `true`/`false` → textes configurés ; `null` → `''`, autre type → exception | oui |
| `CustomColumn` | HTML libre via `template` ou `formatter` (l'un des deux est obligatoire) | non par défaut (`order_field: false`) |
| `ActionColumn` | conteneur d'`Action` | jamais |

Options communes (`AbstractColumn`) : `label` (string ou `false` pour masquer l'en-tête ; défaut =
`ucfirst(trans(nom))`), `template`, `head_css_class`, `cell_css_class`, `formatter`, `show`
(`false` ajoute `d-none` au lieu de retirer la colonne), `raw` (voir « Sécurité » : `false` par
défaut sur les colonnes de valeur, `true` sur `CustomColumn` et `ActionColumn`).

Options supplémentaires des colonnes de valeur (`AbstractValueColumn`) : `field` (property path lu par
PropertyAccess, défaut = nom de la colonne), `order_field` (`null` = `field`, `false` = non triable,
string = champ DQL), `route` + `route_parameters` (array ou callable `fn($result): array`) pour rendre
la cellule cliquable.

**Signatures de `formatter` — elles diffèrent selon la classe** :

- colonne de valeur : `fn(string $valeurTransformee, mixed $result): string`
- `CustomColumn` / `ActionColumn` : `fn(mixed $result): string`
- `Action` : `fn(mixed $result, Action $action): string`

Sur les colonnes de valeur, l'ordre de traitement est : PropertyAccess → `transformValue()` →
`formatter` → `template`. Le template reçoit `{result, cell, column}`.

### Tri et champs joints

`DoctrineDataSource` préfixe automatiquement l'alias racine à tout `order_field` correspondant à
`^[a-z0-9_]+$`. Pour trier sur une jointure, passer un champ qualifié — `'order_field' => 'c.name'` —
et s'assurer que l'alias est bien joint dans le `query_builder`.

---

## Actions

```php
->addAction('delete', [
    'route' => 'app_user_delete',
    'route_parameters' => fn($user) => ['id' => $user->getId()],
    'icon_css_class' => 'fa fa-trash',
    'confirm_message' => 'Supprimer cet utilisateur ?',
    'credential' => 'USER_DELETE',          // is_granted(credential, result)
    'target' => '_blank',
    'stimulus_action' => ['controller' => 'modal', 'action' => 'open', 'parameters' => [...]],
])
```

- Sans `route_parameters`, le template retombe sur `{'id': result.id}` : l'entité doit exposer `getId()`.
- `credential` masque l'action si `is_granted($credential, $result)` est faux.
- `stimulus_action` fusionne automatiquement `entityId: result.id` dans les paramètres.
- Le **template d'une action est résolu par son nom** :
  `@TknowebDatatable/action/_<nom_snake_case>.html.twig`, avec repli sur `_action.html.twig`.
  Ajouter un template dédié suffit à personnaliser une action.
- Surcharge projet des templates du bundle : `templates/bundles/TknowebDatatableBundle/...`
  (ex. `action/_delete.html.twig`, `_datatable_footer_elements_shown.html.twig`).
- Convention des options d'apparence : **non renseignée** vaut `false`, donc « rien » — pas d'icône,
  pas de tooltip, pas de confirmation. Un `null` **explicite** signifie « dérive une valeur » :
  `tooltip` reprend alors le label, `icon_css_class` prend le nom de l'action. `confirm_message` ne
  dérive rien : `null` comme `false` signifie « pas de confirmation ».
- `label` vaut `ucfirst(trans(nom))` par défaut.

Les noms d'action usuels sont déjà traduits dans `translations/messages.fr.yaml`
(`edit`, `delete`, `new`, `show`, `all`, `yes`, `no`, `filter`).

---

## Filtres

Un formulaire de filtre étend `AbstractBaseFilter` (méthode GET, CSRF désactivé, boutons
`filterClear` + `filterSave` ajoutés automatiquement, préfixe de bloc `form_filter_<route>`) :

```php
class UserFilter extends AbstractBaseFilter
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options); // OBLIGATOIRE : boutons + FilterListener

        $builder
            ->add('name', TextFilter::class, [
                'filter_query' => fn(QueryBuilder $qb, $value) => $qb
                    ->andWhere('u.name LIKE :name')
                    ->setParameter('name', '%' . $value . '%'),
            ])
            ->add('active', BoolFilter::class, [
                'filter_query' => function (QueryBuilder $qb, $value) {
                    if ($value !== 'all') {
                        $qb->andWhere('u.active = :active')->setParameter('active', $value === 'true');
                    }
                },
            ]);
    }
}
```

Types disponibles : `TextFilter`, `BoolFilter`, `ChoiceFilter`, `CheckboxFilter`, `DateFilter`
(transforme `Y-m-d`), `MonthFilter` (transforme `Y-m`, widget `<input type="month">` via le thème de
formulaire du bundle), `EntityFilter` (transforme id ↔ entité, mono et multiple ; requiert les options
`class` et `em`).

Règles :

- `filter_query` est **obligatoire** sur chaque champ de filtre et doit être un callable
  `fn($queryBuilder, $value)`. Il doit **muter le QueryBuilder** (`andWhere`, `setParameter`…) :
  retourner un autre objet n'a aucun effet, la valeur de retour est ignorée par l'appelant.
- Le filtre n'est appliqué que si la donnée n'est ni `null` ni `''` — `0` et `false` sont donc filtrants.
- `default_value` pré-remplit le champ ; `BoolFilter` a `default_value: 'false'` et des choix
  `all`/`true`/`false` (chaînes) — le `filter_query` doit gérer explicitement `'all'`.
- La persistance en session est gérée par `FilterListener` (`PRE_SET_DATA` restaure, `PRE_SUBMIT`
  enregistre). `?resetFilters=1` vide les filtres, `?forceFilters[...]` les impose et les écrit en
  session.
- Soumettre le filtre (`filterSave`) remet la pagination à l'offset 0.
- Le bouton `filterClear` est un simple `ButtonType` : c'est au projet de le câbler (lien vers
  `?resetFilters=1` ou JS).
- Chaque champ expose `is_filtered` dans sa vue ; `filterCount()` compte les filtres actifs (badge).

---

## Source de données

`DoctrineDataSource` — options : `query_builder` (`QueryBuilder` ou callable le retournant, requis) et
`hydration_mode` (défaut `HYDRATE_OBJECT`).

Trois requêtes dérivées du QueryBuilder de base :

- **total** : `COUNT(DISTINCT <alias>.id)` sans filtres ni `ORDER BY` ;
- **filtré** : idem + filtres ;
- **résultats** : filtres + `ORDER BY` + `setFirstResult/setMaxResults`, exécuté via
  `Doctrine\ORM\Tools\Pagination\Paginator` (`fetchJoinCollection: true`). L'`ORDER BY` se termine
  par `<alias>.id`, dans le sens du dernier tri (croissant sans tri) : sans ce départage, SQL ne
  garantit pas l'ordre des lignes ex æquo d'une requête à l'autre, et une ligne pourrait s'afficher
  sur deux pages ou sur aucune. Inutile donc d'ajouter un tri sur l'id côté projet. Le départage
  n'est pas ajouté quand `<alias>.id` figure déjà dans l'`ORDER BY`, que ce soit par un tri de la
  datatable (colonne `id`, `order_field` `x.id`) ou par un `orderBy` du `query_builder` : l'ordre
  est alors déjà total, et SQL Server refuse une colonne listée deux fois (erreur 169).

Conséquence : **l'entité racine doit avoir une propriété `id`**. Le QueryBuilder de base est cloné, il
reste réutilisable côté appelant. Une autre source se crée en étendant `AbstractDataSource`
(implémenter `configureOptions`, `initializeDataSources`, `getResults`, `getTotalCount`,
`getFilteredCount`, `setLimitOffset`, `applyLimitMax`).

`applyLimitMax(?int $max): void` est **protected** : elle reçoit `null` quand la limite est illimitée
(`-1`), la normalisation étant faite par `AbstractDataSource::setLimitMax()`. Une source de données
personnalisée doit donc passer ce `null` tel quel à son moteur (`setMaxResults(null)` côté Doctrine).

---

## Sécurité

Le contenu des cellules est **échappé par défaut**. L'option de colonne `raw` gouverne ce
comportement :

| Type de colonne | `raw` par défaut | Rendu |
|---|---|---|
| `TextColumn`, `BoolColumn`, `DateTimeColumn` | `false` | échappé |
| `CustomColumn`, `ActionColumn` | `true` | brut, ces colonnes produisent du HTML par nature |

**Une colonne de valeur qui produit du HTML** — via `template` ou via un `formatter` retournant du
balisage — doit déclarer `'raw' => true` explicitement, sinon son HTML s'affiche en clair :

```php
->addColumn('iconClass', TextColumn::class, [
    'raw' => true,
    'formatter' => fn(string $value) => '<i class="fa ' . $value . '"></i>',
])
```

Dès lors que `'raw' => true` est posé, **l'échappement redevient la responsabilité de l'appelant** :
tout `formatter` ou `template` qui concatène de la donnée utilisateur doit l'échapper lui-même, sous
peine de XSS stocké. Préférer un `template` Twig (échappement automatique des variables) à une
concaténation de chaînes en PHP.

Les actions restent rendues en `raw` : leurs templates sont fournis par le bundle ou par le projet,
jamais par la donnée.

---

## Pièges connus

- Le label d'en-tête est traduit deux fois : `ucfirst(trans(nom))` à la construction, puis `|trans`
  dans `_datatable_thead.html.twig`. Utiliser des clés de traduction idempotentes.
- `resetPagination`, `resetFilters` et `forceFilters` ne sont **pas** préfixés par le nom de la
  datatable : avec deux datatables sur la même page, ils s'appliquent aux deux.
- Les templates de pied de page (`_datatable_footer_*`) contiennent du français en dur
  (« Afficher », « éléments », « Affichage des éléments … ») — les surcharger côté projet si besoin.
- `_datatable_grouped_actions.html.twig` suppose Bootstrap 5 (`dropdown`, `data-bs-toggle`).
- L'affichage « Affichage des éléments X à Y » part de l'offset brut (0-indexé).
- Le bundle n'a ni suite de tests, ni analyse statique, ni CI : toute modification se vérifie
  à la main dans un projet consommateur.
- Les clés `save` et `formSave` de `translations/messages.fr.yaml` ne servent pas au bundle, mais des
  projets s'en servent comme libellés de bouton : ne pas les retirer.

---

## Architecture interne (travail sur le bundle)

```
src/
  TknowebDatatableBundle.php   AbstractBundle : configure() (arbre de conf), loadExtension()
                               (paramètres conteneur), prependExtension() (thème de formulaire Twig,
                               chemin AssetMapper)
  Datatable/
    DatatableFactory.php       service public, create() / createNamed()
    Datatable.php              objet central : colonnes, tri, limites, session, handleRequest()
    DatatableResult.php        une ligne = un résultat + ses cellules
    DatatableResultCell.php    une cellule, calculée à la construction
  Column/                      AbstractColumn → AbstractValueColumn → Text/DateTime/Bool
                               AbstractColumn → CustomColumn, ActionColumn (+ Action)
  DataSource/                  AbstractDataSource → DoctrineDataSource
  Filter/                      AbstractBaseFilter (formulaire), AbstractFilter (champ) + types
                               EventListener/FilterListener (persistance session)
  Twig/                        DatatableExtension, FilterExtension
  Utils/Utils.php              résolution route ↔ contrôleur::action
templates/                     namespace Twig @TknowebDatatable
config/services.yml            services publics ; packages/twig.yml : thème de formulaire
assets/                        contrôleur Stimulus + CSS
translations/messages.fr.yaml
```

Flux d'une requête : `handleRequest()` passe la requête au formulaire de filtre, résout `max`/`offset`
(requête → session → défaut), résout le tri (requête → session → `addOrderBy`), purge les tris
orphelins, réécrit la session, puis appelle `dataSource->initialize(colonnes, tri, limites, formulaire)`.
Le rendu (`renderDatatable`) déclenche l'exécution des requêtes de façon paresseuse et mémoïsée
(`getResults`/`getTotalCount`/`getFilteredCount`, argument `$forceRefresh` pour recalculer).

### Conventions du code

- Options partout via `Symfony\Component\OptionsResolver` : un `configureOptions()` par classe,
  appelant `parent::configureOptions()`, avec défauts issus des paramètres conteneur `datatable.*`.
- API fluide (`return $this`) sur `Datatable`.
- Erreurs de configuration signalées par `\Exception` avec message explicite (pas d'exceptions dédiées).
- Promotion de propriétés en constructeur, propriétés `protected`, types de retour explicites.
- Commentaires et docblocks en anglais ; chaînes visibles utilisateur en français ou traduites.

### Modifier les assets — incrémenter la version npm

**Toute modification sous `assets/` impose d'incrémenter `"version"` dans `assets/package.json`.**

Les projets Encore déclarent le paquet en dépendance `file:vendor/tknoweb/datatable-bundle/assets`.
Yarn compare les versions pour décider s'il recopie le dossier : à version identique, il conserve sa
copie dans `node_modules` et le projet continue de servir l'ancien JavaScript et l'ancien CSS, sans
aucun message. Le PHP est à jour, les assets non — le pire des symptômes, puisqu'il est muet.

Aligner cette version sur le tag Composer est le plus lisible. Le strict nécessaire est qu'elle
change dès que le contenu de `assets/` change.

### Maintenir la documentation à jour

**Toute modification du code du bundle doit être répercutée dans la documentation**, dans le même
temps que le code — pas plus tard. Deux fichiers, deux publics, tous deux à vérifier :

- `CLAUDE.md` (ce fichier) — pour les instances Claude ;
- [README.md](README.md) — pour les développeurs.

Déclencheurs typiques : option ajoutée, renommée ou dont le défaut change ; signature d'un callback
(`formatter`, `filter_query`, `route_parameters`) ; nouveau type de colonne, de filtre ou de source
de données ; nouveau template surchargeable ; changement de comportement du tri, de la pagination ou
de la persistance en session ; correction d'un point listé dans « Pièges connus ».

Un changement purement interne, sans effet observable pour un projet consommateur, ne demande rien.
Dans le doute, mettre à jour : le bundle est utilisé par plusieurs projets, une doc périmée y produit
du code faux simultanément.

Signaler explicitement, en fin de tâche, les fichiers de documentation mis à jour — ou pourquoi
aucun ne l'a été.

### Git

Branches `master` (stable) et `develop`.
Toute modification de comportement impacte plusieurs projets consommateurs : privilégier les options
rétrocompatibles dont la valeur par défaut reproduit le comportement existant.
