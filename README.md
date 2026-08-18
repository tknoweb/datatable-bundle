# T'knoweb Datatable Bundle

Bundle Symfony générant des datatables côté serveur : colonnes typées, tri et pagination persistés en
session, filtres via formulaires Symfony, actions par ligne, rendu Twig avec Turbo Frame et Stimulus.

## Intégration Claude Code dans un projet consommateur

Pour que Claude connaisse mieux ce bundle et produise du code conforme, ajouter ces trois lignes au
`CLAUDE.md` du projet consommateur (le créer à la racine s'il n'existe pas) :

```md
## Datatables
Ce projet utilise le bundle tknoweb/datatable-bundle.
@vendor/tknoweb/datatable-bundle/CLAUDE.md
```

> La ligne `@vendor/...` doit être en **texte brut** : un import placé dans un bloc de code ou entre
> backticks n'est pas évalué.

Ce `CLAUDE.md` étant commité dans le projet, tous les collaborateurs en bénéficient sans installation
supplémentaire, et la documentation lue est toujours celle de la version du bundle réellement
installée.

---

## Préambule

Le projet doit tourner sous **Symfony 6.4** et **PHP 8.1** minimum (contraintes déclarées dans le
`composer.json` du bundle).

Stimulus JS et Turbo ne sont pas obligatoires, mais permettent de recharger les datatables de manière
plus fluide sans rechargement de page.

---

## A) Installation

### A-1) Installation du bundle

```bash
composer require tknoweb/datatable-bundle
```

Puis enregistrer le bundle dans `config/bundles.php` (il n'y a pas de recipe Flex) :

```php
Tknoweb\DatatableBundle\TknowebDatatableBundle::class => ['all' => true],
```

### A-2) Assets

Le contrôleur Stimulus doit être disponible sous le nom `datatable`.

- **AssetMapper** : le chemin `assets/js` du bundle est déclaré automatiquement sous
  `@tknoweb/datatable-bundle`.
- **Webpack Encore** : `assets/package.json` déclare le contrôleur et l'import automatique du CSS.

Si la chaîne d'assets ne l'importe pas d'elle-même, importer `assets/styles/datatable.css` depuis
l'application. Cette feuille stylise les classes `.datatable-*` et le voile de chargement du Turbo
Frame.

### A-3) Paramétrage global du bundle

Créer `config/packages/tknoweb_datatable.yaml`. Toutes les clés sont optionnelles, les valeurs
ci-dessous sont les valeurs par défaut du bundle :

```yaml
tknoweb_datatable:
  column:
    bool_text_true: 'Oui'
    bool_text_false: 'Non'
    date_time_format: 'd/m/Y H:i:s'
    date_format: 'd/m/Y'
    head_css_class: ~        # classe appliquée à tous les <th>
    cell_css_class: ~        # classe appliquée à tous les <td>
  action_column:
    template_style: inline   # inline | grouped
  action:
    css_class: ~             # classe par défaut des actions
  datatable:
    css_class: ~             # classe par défaut de la table
    default_limit_max: 25
    possible_limit_max: [10, 25, 50]
```

La valeur **`-1`** est acceptée par `default_limit_max` et par les entrées de `possible_limit_max` :
elle signifie « afficher tous les résultats, sans pagination ». Toute autre valeur doit être
strictement positive.

```yaml
  datatable:
    default_limit_max: 25
    possible_limit_max: [25, 50, -1]   # -1 = tout afficher
```

---

## B) Intégration

### B-1) Surcharge des templates

Créer un répertoire `templates/bundles/TknowebDatatableBundle/` et y placer les fichiers à
surcharger, en respectant le nommage du bundle. Templates surchargeables :

- `_datatable.html.twig`
- `_datatable_cell.html.twig`
- `_datatable_footer.html.twig`
- `_datatable_footer_elements_shown.html.twig`
- `_datatable_footer_limit_selector.html.twig`
- `_datatable_footer_pagination.html.twig`
- `_datatable_grouped_actions.html.twig`
- `_datatable_inline_actions.html.twig`
- `_datatable_table.html.twig`
- `_datatable_tbody.html.twig`
- `_datatable_thead.html.twig`
- `_datatable_turbo_frame.html.twig`
- `action/_action.html.twig`
- `form/filter_theme.html.twig`

Le rendu d'une action donnée peut aussi être surchargé individuellement en créant
`action/_<nom_action>.html.twig` (`action/_edit.html.twig`, `action/_soft_delete.html.twig`…) : le
bundle résout automatiquement ce template à partir du nom de l'action, avec repli sur
`_action.html.twig`. C'est ce qui permet d'écrire `->addAction('edit')` sans aucune option.

### B-2) Création de la datatable

> **Emplacement du code** : dans les nouveaux projets, la datatable se déclare dans une classe
> dédiée sous `src/Datatable/`, et non dans le contrôleur. Voir la convention détaillée dans
> [CLAUDE.md](CLAUDE.md). L'exemple ci-dessous montre l'enchaînement des appels, valable quel
> que soit l'emplacement.

Les commentaires signalent les éléments optionnels.

```php
public function index(Request $request, DatatableFactory $datatableFactory, ProjectRepository $projectRepository): Response
{
    $datatable = $datatableFactory
        // Le nom doit être unique : il sert de clé de session et de préfixe aux paramètres d'URL
        ->createNamed('project-datatable', ['possible_limit_max' => [10, 25, 50], 'default_limit_max' => 25])
        ->addColumn('name', TextColumn::class, [
            'label' => 'Nom',
            'head_css_class' => 'my-class',      // classe sur le <th>
            'cell_css_class' => 'my-cell-class', // classe sur le <td>
        ])
        ->addColumn('active', BoolColumn::class, ['label' => 'Actif'])
        ->addColumn('createdAt', DateTimeColumn::class, [
            'label' => 'Créé le',
            'display_time' => true,              // affiche ou non la partie heure
        ])
        ->addColumn('actions', ActionColumn::class, ['template_style' => 'inline'])
        ->addAction('edit', [
            'route' => 'projects_edit',
            // Inutile si le seul paramètre passé est l'id
            'route_parameters' => function ($entity) {
                return ['uuid' => $entity->getUuid()];
            },
            'icon_css_class' => 'fa fa-edit',
        ])
        ->setDataSource(DoctrineDataSource::class, [
            'query_builder' => $projectRepository
                ->createQueryBuilder('x')
                // Condition toujours appliquée, quels que soient les filtres
                ->where('x.deactivatedAt IS NULL'),
        ])
        ->addOrderBy('createdAt', 'asc')         // colonne déclarée ci-dessus, et triable
        ->handleRequest($request);

    return $this->render('projects/index.html.twig', [
        'datatable' => $datatable,
    ]);
}
```

> Chaque nom de colonne doit être unique : `addColumn()` indexe par nom, une seconde colonne portant
> le même nom écrase silencieusement la première.

### B-3) Création du formulaire de filtre

```php
// La classe étendue gère la sauvegarde des filtres en session
class ProjectFilter extends AbstractBaseFilter
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Indispensable : ajoute les boutons filtrer/reset et le listener de session
        parent::buildForm($builder, $options);

        $builder
            ->add('name', TextFilter::class, [
                'label' => 'Nom',
                // Filtre qui sera appliqué au QueryBuilder de la datatable
                'filter_query' => function (QueryBuilder $qb, string $filterValue) {
                    return $qb->andWhere('x.name LIKE :name')
                        ->setParameter('name', "%$filterValue%");
                },
            ]);
    }
}
```

Puis associer le formulaire à la datatable :

```php
$datatable = $datatableFactory
    ->createNamed('project-datatable')
    ->setFilterForm(ProjectFilter::class)
    // …
```

### B-4) Affichage

Afficher la datatable dans un template Twig :

```twig
{{ renderDatatable(datatable) }}
```

Le formulaire de filtre n'est **pas** rendu par `renderDatatable()` : il faut le passer à la vue
depuis le contrôleur.

```php
return $this->render('projects/index.html.twig', [
    'datatable' => $datatable,
    'filterForm' => $datatable->getFilterForm(),
]);
```

Puis le rendre comme un formulaire standard :

```twig
{{ form_start(filterForm) }}
    {{ form_row(filterForm.name) }}
    {{ form_row(filterForm.filterClear) }}
    {{ form_row(filterForm.filterSave) }}
    {{ form_rest(filterForm) }}
{{ form_end(filterForm) }}
```

---

## C) Référence des paramètres

### C-1) Datatable

Tous les paramètres sont optionnels.

| Paramètre | Rôle |
|---|---|
| `possible_limit_max` | tableau d'entiers des limites autorisées (ex. `[10, 25]`). `-1` ajoute une entrée « Tout afficher » |
| `default_limit_max` | limite par défaut (ex. `10`). `-1` pour afficher tous les résultats d'emblée |
| `css_class` | classe CSS de la `<table>` |
| `template` | template spécifique de la datatable |
| `template_tbody` | template spécifique du corps de la datatable |
| `scroll_on_change` | avec Stimulus, remonte en haut de page au tri/filtre/navigation |

#### Limite « tout afficher » et pied de page

Avec une limite à `-1`, la datatable affiche tous les résultats sur une seule page : l'offset est
forcé à 0 et la pagination disparaît. Dans le sélecteur de limite, l'entrée `-1` s'affiche avec le
libellé traduit `all` (« Tout » en français).

Le pied de page se masque tout seul quand il n'a plus rien à proposer :

| Situation | Sélecteur de limite | Pagination | Pied de page |
|---|---|---|---|
| plusieurs limites possibles, limite normale | affiché | affichée | affiché |
| une seule limite possible (ex. `[25]`) | masqué | affichée | affiché |
| limite courante à `-1`, plusieurs choix | affiché | masquée | affiché |
| `possible_limit_max: [-1]` | masqué | masquée | **masqué** |

Le compteur d'éléments affichés reste visible dès que le pied de page l'est.

> **Attention aux surcharges existantes.** Si le projet surcharge `_datatable.html.twig` ou
> `_datatable_footer.html.twig` dans `templates/bundles/TknowebDatatableBundle/`, ces copies ne
> contiennent pas les conditions `datatable.displayFooter`, `datatable.displayLimitSelector` et
> `datatable.displayPagination` ajoutées par cette évolution : le pied de page continuera de
> s'afficher et la pagination se comportera mal en mode illimité. Reporter les conditions depuis les
> templates du bundle.

### C-2) Colonnes

| Classe | Rôle |
|---|---|
| `TextColumn` | texte simple ; gère aussi les `BackedEnum` |
| `BoolColumn` | Oui / Non / vide ; libellés paramétrables |
| `DateTimeColumn` | date, heure optionnelle ; format paramétrable |
| `CustomColumn` | contenu libre ; `template` **ou** `formatter` obligatoire |
| `ActionColumn` | colonne contenant des `Action` ; style `inline` ou `grouped` |
| `Action` | action contenue dans une `ActionColumn`, à déclarer après celle-ci |

Paramètres communs à toutes les colonnes — tous optionnels :

| Paramètre | Rôle |
|---|---|
| `label` | le libellé, ou `false` pour ne rien afficher |
| `head_css_class` | classe CSS du `<th>` |
| `cell_css_class` | classe CSS du `<td>` |
| `formatter` | callback de mise en forme (voir signatures ci-dessous) |
| `template` | template d'affichage de la cellule |
| `show` | à `false`, la colonne est présente mais masquée |
| `raw` | à `true`, le contenu n'est pas échappé (voir ci-dessous) |

**Échappement du contenu.** Les cellules sont échappées par défaut sur `TextColumn`, `BoolColumn` et
`DateTimeColumn`. `CustomColumn` et `ActionColumn` ne le sont pas, puisqu'elles produisent du HTML
par nature. Une colonne de valeur qui produit du HTML — par un `template` ou par un `formatter`
retournant du balisage — doit donc déclarer `'raw' => true`, faute de quoi son HTML s'affichera en
clair :

```php
->addColumn('iconClass', TextColumn::class, [
    'raw' => true,
    'formatter' => function (string $value) {
        return '<i class="fa ' . $value . '"></i>';
    },
])
```

Avec `'raw' => true`, l'échappement des données utilisateur redevient à la charge de l'appelant.
Passer par un `template` Twig plutôt que par une concaténation PHP évite le piège.

**Signatures de `formatter` — elles diffèrent selon la classe :**

| Classe | Signature |
|---|---|
| `TextColumn`, `BoolColumn`, `DateTimeColumn` | `fn(string $valeurTransformee, mixed $entite): string` |
| `CustomColumn`, `ActionColumn` | `fn(mixed $entite): string` |
| `Action` | `fn(mixed $entite, Action $action): string` |

**Articulation `formatter` / `template` :**

- Colonnes de valeur (`TextColumn`, `BoolColumn`, `DateTimeColumn`) : la valeur passe par
  `formatter`, **puis** le `template` est rendu avec cette valeur dans la variable `cell`. Les deux
  se cumulent.
- `CustomColumn`, `ActionColumn`, `Action` : si un `formatter` est défini, il est utilisé **et le
  `template` est ignoré**. L'un des deux est obligatoire.

Les colonnes autres que `ActionColumn` supportent également :

| Paramètre | Rôle |
|---|---|
| `field` | si la propriété à afficher diffère du nom de la colonne |
| `order_field` | champ utilisé pour le tri, `false` pour interdire le tri. Une expression DQL est acceptée (ex. `CASE WHEN p.id > 10 THEN p.name ELSE p.code END`) |
| `route` / `route_parameters` | rend la cellule cliquable |

Le sous-élément `Action` supporte :

| Paramètre | Rôle |
|---|---|
| `label` | libellé, ou `false` |
| `tooltip` | contenu de l'attribut `title` ; `false` pour ne rien afficher, `null` reprend le label |
| `route` | nom de la route |
| `route_parameters` | tableau ou callback recevant l'entité de la ligne |
| `icon_css_class` | icône de l'action ; aucune icône si non renseigné, un `null` explicite prend le nom de l'action |
| `css_class` | classe du bloc entourant l'action |
| `confirm_message` | message de confirmation ; `false` ou `null` pour ne pas en demander |
| `credential` | permission conditionnant l'affichage (ex. `PERMISSION_PROJECT_SHOW`) ; appelle `is_granted` avec l'entité |
| `template` | template spécifique ; résolu automatiquement depuis le nom de l'action si absent |
| `formatter` | callback recevant l'entité et l'action courante ; permet un rendu conditionnel via `$action->renderTemplate($result)` |
| `stimulus_action` | action JS Stimulus déclenchée au clic |
| `target` | pour ouvrir en `_blank` |

### C-3) Filtres

Tous les filtres acceptent `default_value` et `required`, et étendent leur type Symfony natif dont
ils acceptent tous les paramètres.

| Classe | Rôle | Étend |
|---|---|---|
| `TextFilter` | texte | `TextType` |
| `DateFilter` | date sans heure, input `single_text` | `DateType` |
| `MonthFilter` | mois (`AAAA-MM`), input `month` | `DateType` |
| `BoolFilter` | Oui / Non / Tous, choix paramétrables | `ChoiceType` |
| `CheckboxFilter` | case à cocher | `CheckboxType` |
| `EntityFilter` | valeur (id) d'entité, mono ou multiple | `EntityType` |
| `ChoiceFilter` | liste de choix | `ChoiceType` |

Le paramètre **`filter_query` est obligatoire** sur chaque champ. C'est un callback recevant le
QueryBuilder de la datatable et la valeur filtrée.

> Il doit **modifier le QueryBuilder en place** (`andWhere()`, `setParameter()`…). Retourner `$qb`
> est la convention de lecture retenue dans nos projets, mais la valeur de retour est ignorée par le
> bundle : retourner un QueryBuilder différent de celui reçu n'aurait aucun effet.

Le filtre n'est appliqué que si la valeur soumise n'est ni `null` ni `''` ; `0` et `false` sont donc
des valeurs filtrantes. `BoolFilter` a par défaut `default_value: 'false'` et des choix sous forme de
chaînes (`'all'`, `'true'`, `'false'`) : son `filter_query` doit traiter explicitement le cas
`'all'`.

La classe du formulaire de filtre doit étendre **`AbstractBaseFilter`**, qui ajoute les éléments
**`filterClear`** (bouton) et **`filterSave`** (submit) et gère la sauvegarde et la suppression des
filtres en session.

---

## Licence

MIT — voir [LICENSE](LICENSE).
