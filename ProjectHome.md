**libmonopoly** est une interface au [dossier étudiant](http://www.dossier.polymtl.ca/) de l'École Polytechnique de Montréal écrite en PHP.

La nouvelle version orientée objet permet désormais les fonctionnalités suivantes :
  * connexion au système de l'École
  * obtenir le statut d'un cours (principalement le nombre de places restant en période de modification de choix de cours)
  * obtenir les informations sur un cours (horaire, nombre de crédits, nom officiel selon le système, projet final, stage)
  * effectuer une modification de choix de cours (ajout/supression et visualisation des cours lorsqu'en période de modification de choix de cours)
  * obtention des notes finales du trimestre lorsqu'en période de fin de session

Ainsi, vérifier périodiquement si une place est disponible dans un cours a priori plein et s'y inscrire automatiquement devient un jeu d'enfant :

```
<?php
         require_once("Monopoly.php");

         set_time_limit(0);
         
         $cours = new MonopolyCourse();
         $cours->abbr = 'inf4705'; // sigle du cours
         $cours->gr_theo = 1; // groupe théorique
         $cours->gr_lab = 1; // groupe de laboratoire
         $config = new MonopolyConfig('code', 'mot_de_passe', '880614');

         $done = false;
         while (!$done) {
                 $mman = new MonopolyManager($config);
                 $status_theo = $mman->get_course_status($cours->abbr, MonopolyManager::COURSE_THEO, $cours->gr_theo);
                 $status_lab = $mman->get_course_status($cours->abbr, MonopolyManager::COURSE_LAB, $cours->gr_lab);
                 if ($status_theo->places > 0 && $status_lab->places > 0) {
                         $mman->add_to_registered_courses($cours); // ajouter le cours
                         $mman->submit_registered_courses(); // soumettre les changements
                         $done = true;
                 } else {
                         sleep(120); // attendre 2 minutes
                 }
         }
?>
```