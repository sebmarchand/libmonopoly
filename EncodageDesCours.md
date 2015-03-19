On trouve dans la source de la page de choix de cours les informations sur tous les cours. Elle est présentée dans un tableau JavaScript sous la forme suivante :

```
var CO = Array();
...
CO["MEC1410"]="NNN020000TL 08STATIQUE";
CO["MEC1415"]="NNN030000TLS26STATIQUE ET RESIST. MATER.";
CO["MEC1420"]="NNN030000TL 26RESISTANCE DES MATERIAUX I000059MEC1410|ING1010|PHS1101A|PHS1101C|PHS1101|PHS1101N|MEC1410N024MTH1006|ING1006|MTH1006N";
...
```

La signification des champs importants est présentée ici :

<pre>
NNO060204TL 30<br>
|||| | | |   |<br>
|||| | | |   `-> nombre de caractères du titre du cours (qui suit immédiatement)<br>
|||| | | `-----> 3 caractères; combinaison de 'T', 'L' et 'S' :<br>
|||| | |           'T' : cours comporte une partie théorique<br>
|||| | |           'L' : cours comporte une partie laboratoire<br>
|||| | |           'S' : les groupes théo. et labo. peuvent être choisis indépendamment<br>
|||| | `-------> 2 caractères; pour les cours sur deux sessions (généralement les projets),<br>
|||| |           nombre de crédits pour la deuxième session<br>
|||| `---------> 2 caractères; pour les cours sur deux sessions (généralement les projets),<br>
||||             nombre de crédits pour la première session<br>
|||`-----------> 2 caractères; nombre de crédits<br>
||`------------> 'O' ou 'N' : cours projet final qui s'échelonne sur deux sessions (à confirmer)<br>
|`-------------> 'O' ou 'N' : est un cours projet final (à confirmer)<br>
`--------------> 'O' ou 'N' : est un cours stage<br>
</pre>

La suite est moins importante pour nous. Il s'agit de la liste de prérequis et de corequis. Les deux listes sont encodées comme le titre du cours, soit une chaine de caractères précédée de sa longueur. La chaine de caractère semble représenter une expression booléenne déterminant si les prérequis/corequis sont respectés.