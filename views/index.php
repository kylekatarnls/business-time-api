<h2>
    API HTTP et Script pour trouver les villes à partir d'un code postal et code postaux à partir d'une ville
</h2>
<p>
    Vicopo est un moyen léger et rapide rechercher une ville française et implémenter des propositions à
    la volée, l'autocomplétion d'un champ de ville ou de code postal et la conversion de l'un vers l'autre.
</p>
<p>
    Testez-la ci-dessous en tapant le début d'un code postal ou du nom d'une ville&nbsp;:
</p>
<section>
    <div class="half">
        <input id="code" placeholder="Code postal" autocomplete="off" autofocus>
    </div>
    <div class="half">
        <input id="city" placeholder="Ville" autocomplete="off">
    </div>
</section>
<div id="output"></div>
<p>
    API jQuery :
</p>
<ul>
    <li>
        Version compressée&nbsp;:
        <a href="<?php echo HOST; ?>/vicopo.min.js" target="_blank"><?php echo HOST; ?>/vicopo.min.js</a>
    </li>
    <li>
        Source&nbsp;:
        <a href="<?php echo HOST; ?>/vicopo.js" target="_blank"><?php echo HOST; ?>/vicopo.js</a>
    </li>
</ul>
<h3>
    Afficher les villes possibles dans une liste
</h3>
<?php jsfiddle('y27x72ka/40', 'html,result'); ?>
<p>
    Placez le code ci-dessus n'importe où sur votre page,
    et insérez le script après jQuery (par exemple avant <code>&lt;/body&gt;</code>) :
</p>
<div class="example static no-header">
    <pre class="json"><span class="tag">&lt;script</span> <span class="key">src</span>=<span class="string">"https://code.jquery.com/jquery-3.5.1.min.js"</span><span class="tag">&gt;&lt;/script&gt;</span>
<span class="tag">&lt;script</span> <span class="key">src</span>=<span class="string">"vicopo.min.js"</span><span class="tag">&gt;&lt;/script&gt;</span></pre>
</div>
<p>
    Ajoutez l'attribut <code>data-vicopo</code> à un élément et passez-lui en paramètre un
    sélecteur qui pointera vers un champ(<code>&lt;input&gt;</code>,
    <code>&lt;select&gt;</code> ou <code>&lt;textarea&gt;</code>).
    Quand la valeur du champs change, l'élément sera duppliqué autant de fois
    qu'il y a de villes commençant par la valeur tapée ou dont le code postal
    commence par la valeur tapée (la recherche commence à partir de 2 caractères tapés).
</p>
<p>
    À l'intérieur de ces éléments, les balises portant les attributs
    <code>data-vicopo-code-postal</code>, <code>data-vicopo-ville</code>
    seront respectivement pourvus du code postal et de la ville. Si ces balises
    sont des champs, utilisez <code>data-vicopo-val-code-postal</code> et
    <code>data-vicopo-val-ville</code> pour que les informations soient assignées
    en tant que valeur.
</p>
<h3>
    Remplir le champ au clic sur un des choix
</h3>
<?php jsfiddle('ntwycok6/11', 'html,result'); ?>
<p>
    Ajoutez l'attribut <code>data-vicopo-click</code> à ce même élément pour rendre les
    choix clicables, cet attribut prend en valeur un objet JSON dont les clés sont les
    sélecteurs et les valeur la donnée à insérer au clic (<code>"ville"</code>,
    <code>"code"</code> ou n'importe quel texte dont les mots "code" et "ville" seront
    remplacé par le code postal et la ville du choix sur lequel l'utilisateur a cliqué.
</p>
<p>
    Alternativement, ajoutez l'attribut <code>data-vicopo-get="ville"</code> à l'élement
    de saisie (input, textarea, etc.) et remplir le champ avec la ville au clic.
</p>
<p>
    Utilisez <code>data-vicopo-get="code"</code> pour remplir le champ avec le code postal
    ou <code>data-vicopo-get="code ville"</code> / <code>data-vicopo-get="ville code"</code>
    pour obtenir les 2 dans l'ordre voulu.
</p>
<h3>
    Compléter le champ avec le premier nom de ville trouvé
</h3>
<?php jsfiddle('48uuL3v5/15'); ?>
<p>
    Lors de l'appui sur <code>Entrée</code>, on récupère la première ville et
    on l'applique comme nouvelle valeur du champ.
</p>
<p>
    L'ajout optionnel de <code>.vicopoTargets().vicopoClean()</code> permet
    d'effacer la liste de suggestions.
</p>
<h3>
    Récupérer les villes au fur et à mesure de la saisie
</h3>
<?php jsfiddle('yk0tu38n/51'); ?>
<p>
    Les méthodes <code>.vicopo()</code>, <code>.codePostal()</code>
    et <code>.ville()</code> appliquées à un élément jQuery permettent
    de récupérer dans une variable le résultat de la recherche
    à chaque lettre saisie dans le champ.
</p>
<h3>
    Utilisation sans champs de saisie
</h3>
<?php jsfiddle('ny8k9ya6/25'); ?>
<p>
    Les méthodes <code>$.vicopo()</code>, <code>$.codePostal()</code>
    et <code>$.ville()</code> prennent en premier paramètre le code
    postal ou la ville (partiel ou entier) recherché et en second
    paramètre une fonction de callback appelée avec le terme recherché
    en premier paramètre, les villes trouvées en second et en troisième
    'code' pour une recherche de code postal ou 'city' pour une
    recherche de ville.
</p>
<h3>
    API HTTP brute au fomart JSON (par défaut)
</h3>
<div class="example static">
    <code class="url">https://vicopo.selfbuild.fr/<span class="key">cherche</span>/<span class="string">680</span></code>
    <pre class="json">{
<span class="key">input</span>: <span class="string">"680"</span>,
<span class="key">cities</span>: [
    {
        <span class="key">code</span>: <span class="number">68040</span>,
        <span class="key">city</span>: <span class="string">"INGERSHEIM"</span>
    },
    {
        <span class="key">code</span>: <span class="number">68000</span>,
        <span class="key">city</span>: <span class="string">"COLMAR"</span>
    }
]
}</pre>
</div>
<p>
    Le paramètre <code>cherche</code> (ou <code>search</code>) permet de faire une
    recherche parmi les villes et codes postaux. Utilisez <code>ville</code>
    (ou <code>city</code>) pour chercher uniquement dans les villes, <code>code</code>
    ou <code>code-postal</code> pour chercher dans les codes postaux
    seulement.
</p>
<h3>
    API HTTP brute au fomart JSONP (avec callback)
</h3>
<div class="example static">
    <code class="url">https://vicopo.selfbuild.fr/<span class="key">ville</span>/<span class="string">mul</span>?<span class="key">format</span>=<span class="string">callback</span></code>
    <pre class="json"><span class="string"><strong>maFonction</strong></span>(
{
    <span class="key">input</span>: <span class="string">"mul"</span>,
    <span class="key">cities</span>: [
        {
            <span class="key">code</span>: <span class="number">67350</span>,
            <span class="key">city</span>: <span class="string">"MULHAUSEN"</span>
        },
        {
            <span class="key">code</span>: <span class="number">78790</span>,
            <span class="key">city</span>: <span class="string">"MULCENT"</span>
        },
        {
            <span class="key">code</span>: <span class="number">41500</span>,
            <span class="key">city</span>: <span class="string">"MULSANS"</span>
        },
        {
            <span class="key">code</span>: <span class="number">72230</span>,
            <span class="key">city</span>: <span class="string">"MULSANNE"</span>
        },
        {
            <span class="key">code</span>: <span class="number">68100</span>,
            <span class="key">city</span>: <span class="string">"MULHOUSE"</span>
        },
        {
            <span class="key">code</span>: <span class="number">68200</span>,
            <span class="key">city</span>: <span class="string">"MULHOUSE"</span>
        },
        {
            <span class="key">code</span>: <span class="number">57260</span>,
            <span class="key">city</span>: <span class="string">"MULCEY"</span>
        }
    ]
}
)</pre>
</div>
<p>
    Ce format JSONP peut être utilisé pour exécuter du code DSL (Dynamic Script Loading)
</p>
<?php jsfiddle('65j02buu/9'); ?>
<h3>
    API au format XML
</h3>
<div class="example static">
    <code class="url">https://vicopo.selfbuild.fr/<span class="key">code</span>/<span class="string">680</span>?<span class="key">format</span>=<span class="string">xml</span></code>
    <pre class="json"><span class="tag">&lt;vicopo&gt;</span>
<span class="tag">&lt;input&gt;</span>680<span class="tag">&lt;/input&gt;</span>
<span class="tag">&lt;cities&gt;</span>
    <span class="tag">&lt;city&gt;</span>INGERSHEIM<span class="tag">&lt;/city&gt;</span>
    <span class="tag">&lt;code&gt;</span>68040<span class="tag">&lt;/code&gt;</span>
<span class="tag">&lt;/cities&gt;</span>
<span class="tag">&lt;cities&gt;</span>
    <span class="tag">&lt;city&gt;</span>COLMAR<span class="tag">&lt;/city&gt;</span>
    <span class="tag">&lt;code&gt;</span>68000<span class="tag">&lt;/code&gt;</span>
<span class="tag">&lt;/cities&gt;</span>
<span class="tag">&lt;/vicopo&gt;</pre>
</div>
<h3>
    API au format YAML
</h3>
<div class="example static">
    <code class="url">https://vicopo.selfbuild.fr/<span class="key">ville</span>/<span class="string">argel</span>?<span class="key">format</span>=<span class="string">yaml</span></code>
    <pre class="json">---
input: <span class="string">argel</span>
cities:
- code: <span class="number">65400</span>
city: <span class="string">ARGELES-GAZOST</span>
- code: <span class="number">40700</span>
city: <span class="string">ARGELOS</span>
- code: <span class="number">64450</span>
city: <span class="string">ARGELOS</span>
- code: <span class="number">65200</span>
city: <span class="string">ARGELES-BAGNERES</span>
- code: <span class="number">34380</span>
city: <span class="string">ARGELLIERS</span>
- code: <span class="number">11120</span>
city: <span class="string">ARGELIERS</span>
- code: <span class="number">66700</span>
city: <span class="string">ARGELES-SUR-MER</span>
- code: <span class="number">40430</span>
city: <span class="string">ARGELOUSE</span>
...</pre>
</div>
<h3>
    Utilisation côté serveur ou dans un programme
</h3>
<p>
    Comme n'importe quel web-service HTTP, vous pouvez appeler Vicopo
    depuis un programme ou un serveur en utilisant votre langage préféré
    (PHP, Node.js, Python, Ruby, C, C#, etc.), voici un exemple en PHP :
</p>
<div class="example static">
    <div>
        afficheCodePostalLille.php
    </div>
    <pre class="json"><span class="key">$ville</span> = <span class="string">'Lille'</span>;
<span class="key">$vicopoUrl</span> = <span class="string">'http://vicopo.selfbuild.fr/city/'</span> . <span class="number">urlencode</span>(<span class="key">$ville</span>);
<span class="key">$json</span> = @<span class="number">json_decode</span>(<span class="number">file_get_contents</span>(<span class="key">$vicopoUrl</span>));
<span class="key">$codePostal</span> = ! <span class="number">is_object</span>(<span class="key">$json</span>) || <span class="number">empty</span>(<span class="key">$json</span>-><span class="key">cities</span>) ? <span class="string">'introuvable'</span> : <span class="key">$json</span>-><span class="key">cities</span>[<span class="number">0</span>]-><span class="key">code</span>;
<span class="number">echo</span> <span class="key">$codePostal</span>;</pre>
</div>
<h3>
    Package Composer
</h3>
<p>
    Vicopo est disponible via <code>composer require kylekatarnls/vicopo</code> :
</p>
<div class="example static">
    <div>
        afficheCodePostalLille.php
    </div>
    <pre class="json"><span style="color: maroon;">&lt;?php</span>
<span class="number">use</span> Vicopo\Vicopo;
<span class="number">print_r</span>(<span class="key">Vicopo</span>::<span class="number">http</span>(<span class="number">75001</span>));
<span class="number">print_r</span>(<span class="key">Vicopo</span>::<span class="number">https</span>(<span class="string">'paris'</span>));</pre>
</div>
<h3>
    Plugin node.js
</h3>
<p>
    Vicopo est disponible sous node.js directement via <code>npm install vicopo</code>
    dans un invité de commande puis <code>require('vicopo')</code> :
</p>
<div class="example static">
    <div>
        afficheCodePostalLille.js
    </div>
    <pre class="json">var <span class="key">ville</span> = <span class="string">'Lille'</span>;
var <span class="key">vicopo</span> = <span class="number">require</span>(<span class="string">'vicopo'</span>);
<span class="number">vicopo</span>(ville, <span class="number">function</span> (err, cities) {
<span class="number">if</span> (err) {
    <span class="number">throw</span> err;
} else {
    <span class="number">console.log</span>(cities);
}
});</pre>
</div>
<h3>
    Gem Ruby
</h3>
<p>
    Vicopo est disponible sous Ruby directement via <code>gem install vicopo</code>
    dans un invité de commande puis <code>require 'vicopo'</code> :
</p>
<div class="example static">
    <div>
        afficheCodePostalLille.rb
    </div>
    <pre class="json"><span class="number">require</span> <span class="string">'vicopo'</span>
<span class="key">ville</span> = <span class="string">'Lille'</span>
puts <span class="number">Vicopo.http</span> ville</pre>
</div>
<h3>
    Package Python
</h3>
<p>
    Vicopo est disponible sous Python directement via <code>pip install vicopo</code>
    dans un invité de commande puis <code>require 'vicopo'</code> :
</p>
<div class="example static">
    <div>
        afficheCodePostalLille.py
    </div>
    <pre class="json"><span class="number">from</span> Vicopo <span class="number">import</span> Vicopo

<span class="key">ville</span> = Vicopo.http(<span class="number">75001</span>)

<span class="key">ville</span> = Vicopo.http(<span class="string">'paris'</span>)</pre>
</div>
<h3>
    Liens du plug-in vicopo
</h3>
<ul>
    <?php
    foreach ([
        'GitHub' => 'https://github.com/kylekatarnls/vicopo',
        'NPM' => 'https://www.npmjs.com/package/vicopo',
        // 'jQuery Plugins' => 'https://www.npmjs.com/browse/keyword/jquery-plugin',
        'Gem Ruby' => 'https://rubygems.org/gems/vicopo',
        'Package Python' => 'https://pypi.python.org/pypi/Vicopo',
        'Package Composer' => 'https://packagist.org/packages/kylekatarnls/vicopo',
    ] as $name => $link) {
        ?>
        <li><?php echo $name; ?> : <a href="<?php echo $link; ?>"><?php echo $link; ?></a></li>
        <?php
    }
    ?>
</ul>
