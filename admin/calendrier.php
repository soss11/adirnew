<?php
/**
 * Mini CRM - Calendrier des événements
 */

$pageTitle = 'Calendrier';
require_once __DIR__ . '/../includes/header.php';

requireLogin();
requireRole(['admin', 'gestionnaire']);

$eventTypes = [
    'spectacle' => ['label' => 'Spectacle', 'color' => '#e91e63'],
    'soiree' => ['label' => 'Soirée', 'color' => '#9c27b0'],
    'tombola' => ['label' => 'Tombola', 'color' => '#ff9800'],
    'atelier' => ['label' => 'Atelier', 'color' => '#4caf50'],
    'reunion' => ['label' => 'Réunion', 'color' => '#2196f3'],
    'autre' => ['label' => 'Autre', 'color' => '#607d8b']
];

// Vue (mois ou semaine)
$vue = $_GET['vue'] ?? 'mois';

// Date courante
$annee = isset($_GET['annee']) ? (int)$_GET['annee'] : (int)date('Y');
$mois = isset($_GET['mois']) ? (int)$_GET['mois'] : (int)date('m');
$semaine = isset($_GET['semaine']) ? (int)$_GET['semaine'] : (int)date('W');

// Noms des mois et jours en français
$moisNoms = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
             'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
$jourNoms = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
$jourNomsComplets = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

// Récupérer les événements
if ($vue === 'mois') {
    $debutPeriode = "$annee-$mois-01";
    $finPeriode = date('Y-m-t', strtotime($debutPeriode));

    // Navigation
    $moisPrecedent = $mois - 1;
    $anneePrecedente = $annee;
    if ($moisPrecedent < 1) { $moisPrecedent = 12; $anneePrecedente--; }

    $moisSuivant = $mois + 1;
    $anneeSuivante = $annee;
    if ($moisSuivant > 12) { $moisSuivant = 1; $anneeSuivante++; }
} else {
    // Vue semaine
    $dto = new DateTime();
    $dto->setISODate($annee, $semaine);
    $debutPeriode = $dto->format('Y-m-d');
    $dto->modify('+6 days');
    $finPeriode = $dto->format('Y-m-d');

    // Navigation
    $semainePrecedente = $semaine - 1;
    $anneePrecedente = $annee;
    if ($semainePrecedente < 1) { $semainePrecedente = 52; $anneePrecedente--; }

    $semaineSuivante = $semaine + 1;
    $anneeSuivante = $annee;
    if ($semaineSuivante > 52) { $semaineSuivante = 1; $anneeSuivante++; }
}

$stmt = $pdo->prepare("SELECT e.*,
    (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id = e.id AND i.statut != 'annule') as nb_inscrits
    FROM evenements e
    WHERE DATE(e.date_debut) BETWEEN ? AND ?
    ORDER BY e.date_debut ASC");
$stmt->execute([$debutPeriode, $finPeriode]);
$evenements = $stmt->fetchAll();

// Organiser les événements par jour
$eventsParJour = [];
foreach ($evenements as $event) {
    $jour = date('Y-m-d', strtotime($event['date_debut']));
    if (!isset($eventsParJour[$jour])) {
        $eventsParJour[$jour] = [];
    }
    $eventsParJour[$jour][] = $event;
}
?>

<style>
.calendar-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.calendar-nav h2 {
    margin: 0;
}
.calendar-nav .nav-buttons {
    display: flex;
    gap: 10px;
}
.vue-toggle {
    display: flex;
    gap: 5px;
    background: #f0f0f0;
    padding: 4px;
    border-radius: 8px;
}
.vue-toggle a {
    padding: 8px 16px;
    text-decoration: none;
    color: #666;
    border-radius: 6px;
    font-size: 14px;
}
.vue-toggle a.active {
    background: #fff;
    color: var(--primary);
    font-weight: 500;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

/* Calendrier mensuel */
.calendar-month {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background: #ddd;
    border-radius: 10px;
    overflow: hidden;
}
.calendar-month .header {
    background: var(--primary);
    color: #fff;
    padding: 12px;
    text-align: center;
    font-weight: 600;
}
.calendar-month .day {
    background: #fff;
    min-height: 100px;
    padding: 8px;
}
.calendar-month .day.other-month {
    background: #f9f9f9;
}
.calendar-month .day.today {
    background: rgba(102, 126, 234, 0.1);
}
.calendar-month .day .day-number {
    font-weight: 600;
    margin-bottom: 5px;
    color: #333;
}
.calendar-month .day.other-month .day-number {
    color: #aaa;
}
.calendar-month .day.today .day-number {
    color: var(--primary);
    background: var(--primary);
    color: #fff;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.calendar-event {
    background: #667eea;
    color: #fff;
    font-size: 11px;
    padding: 3px 6px;
    border-radius: 4px;
    margin-bottom: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
    text-decoration: none;
    display: block;
}
.calendar-event:hover {
    opacity: 0.9;
}
.more-events {
    font-size: 11px;
    color: #666;
    text-align: center;
    cursor: pointer;
}

/* Vue semaine */
.calendar-week {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 15px;
}
.week-day {
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}
.week-day.today {
    box-shadow: 0 0 0 2px var(--primary);
}
.week-day-header {
    background: var(--light-gray);
    padding: 12px;
    text-align: center;
}
.week-day-header .day-name {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
}
.week-day-header .day-num {
    font-size: 24px;
    font-weight: 700;
    color: #333;
}
.week-day.today .week-day-header {
    background: var(--primary);
}
.week-day.today .week-day-header .day-name,
.week-day.today .week-day-header .day-num {
    color: #fff;
}
.week-day-content {
    padding: 10px;
    min-height: 150px;
}
.week-event {
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 8px;
    color: #fff;
    text-decoration: none;
    display: block;
}
.week-event:hover {
    opacity: 0.9;
}
.week-event .event-time {
    font-size: 11px;
    opacity: 0.9;
    margin-bottom: 3px;
}
.week-event .event-title {
    font-weight: 600;
    font-size: 13px;
}
.week-event .event-lieu {
    font-size: 11px;
    opacity: 0.8;
    margin-top: 3px;
}

/* Modal événement */
.event-modal {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.event-modal.active {
    display: flex;
}
.event-modal-content {
    background: #fff;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}
</style>

<div class="card">
    <div class="calendar-nav">
        <div class="nav-buttons">
            <?php if ($vue === 'mois'): ?>
                <a href="?vue=mois&annee=<?php echo $anneePrecedente; ?>&mois=<?php echo $moisPrecedent; ?>" class="btn btn-secondary">&larr; Précédent</a>
            <?php else: ?>
                <a href="?vue=semaine&annee=<?php echo $anneePrecedente; ?>&semaine=<?php echo $semainePrecedente; ?>" class="btn btn-secondary">&larr; Précédent</a>
            <?php endif; ?>

            <a href="?vue=<?php echo $vue; ?>" class="btn btn-secondary">Aujourd'hui</a>
        </div>

        <h2>
            <?php if ($vue === 'mois'): ?>
                <?php echo $moisNoms[$mois] . ' ' . $annee; ?>
            <?php else: ?>
                <?php
                $dto = new DateTime();
                $dto->setISODate($annee, $semaine);
                echo 'Semaine du ' . $dto->format('d') . ' ';
                echo $moisNoms[(int)$dto->format('m')] . ' ' . $annee;
                ?>
            <?php endif; ?>
        </h2>

        <div style="display:flex;gap:15px;align-items:center;">
            <div class="vue-toggle">
                <a href="?vue=mois&annee=<?php echo $annee; ?>&mois=<?php echo $mois; ?>" class="<?php echo $vue === 'mois' ? 'active' : ''; ?>">Mois</a>
                <a href="?vue=semaine&annee=<?php echo $annee; ?>&semaine=<?php echo date('W', strtotime("$annee-$mois-15")); ?>" class="<?php echo $vue === 'semaine' ? 'active' : ''; ?>">Semaine</a>
            </div>

            <?php if ($vue === 'mois'): ?>
                <a href="?vue=mois&annee=<?php echo $anneeSuivante; ?>&mois=<?php echo $moisSuivant; ?>" class="btn btn-secondary">Suivant &rarr;</a>
            <?php else: ?>
                <a href="?vue=semaine&annee=<?php echo $anneeSuivante; ?>&semaine=<?php echo $semaineSuivante; ?>" class="btn btn-secondary">Suivant &rarr;</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($vue === 'mois'): ?>
    <!-- Vue Mensuelle -->
    <div class="calendar-month">
        <?php foreach ($jourNoms as $jour): ?>
            <div class="header"><?php echo $jour; ?></div>
        <?php endforeach; ?>

        <?php
        $premierJour = date('N', strtotime("$annee-$mois-01"));
        $nbJours = date('t', strtotime("$annee-$mois-01"));
        $aujourdhui = date('Y-m-d');

        // Jours du mois précédent
        $moisPrecNum = $mois - 1;
        $anneePrecNum = $annee;
        if ($moisPrecNum < 1) { $moisPrecNum = 12; $anneePrecNum--; }
        $nbJoursPrecedent = date('t', strtotime("$anneePrecNum-$moisPrecNum-01"));

        for ($i = 1; $i < $premierJour; $i++):
            $jourPrec = $nbJoursPrecedent - ($premierJour - 1 - $i);
        ?>
            <div class="day other-month">
                <div class="day-number"><?php echo $jourPrec; ?></div>
            </div>
        <?php endfor; ?>

        <?php for ($jour = 1; $jour <= $nbJours; $jour++):
            $dateJour = sprintf('%04d-%02d-%02d', $annee, $mois, $jour);
            $isToday = $dateJour === $aujourdhui;
            $eventsJour = $eventsParJour[$dateJour] ?? [];
        ?>
            <div class="day <?php echo $isToday ? 'today' : ''; ?>">
                <div class="day-number" <?php if($isToday): ?>style="display:inline-flex;"<?php endif; ?>><?php echo $jour; ?></div>
                <?php
                $maxEvents = 3;
                $count = 0;
                foreach ($eventsJour as $event):
                    if ($count >= $maxEvents) break;
                    $type = $eventTypes[$event['type']] ?? $eventTypes['autre'];
                    $count++;
                ?>
                    <a href="evenements.php?action=view&id=<?php echo $event['id']; ?>"
                       class="calendar-event"
                       style="background:<?php echo $type['color']; ?>;"
                       title="<?php echo e($event['titre']); ?>">
                        <?php echo date('H:i', strtotime($event['date_debut'])); ?> - <?php echo e($event['titre']); ?>
                    </a>
                <?php endforeach; ?>
                <?php if (count($eventsJour) > $maxEvents): ?>
                    <div class="more-events">+<?php echo count($eventsJour) - $maxEvents; ?> autre(s)</div>
                <?php endif; ?>
            </div>
        <?php endfor; ?>

        <?php
        // Jours du mois suivant
        $cellulesTotales = $premierJour - 1 + $nbJours;
        $cellulesRestantes = 7 - ($cellulesTotales % 7);
        if ($cellulesRestantes < 7):
            for ($j = 1; $j <= $cellulesRestantes; $j++):
        ?>
            <div class="day other-month">
                <div class="day-number"><?php echo $j; ?></div>
            </div>
        <?php
            endfor;
        endif;
        ?>
    </div>

    <?php else: ?>
    <!-- Vue Semaine -->
    <div class="calendar-week">
        <?php
        $dto = new DateTime();
        $dto->setISODate($annee, $semaine);
        $aujourdhui = date('Y-m-d');

        for ($i = 0; $i < 7; $i++):
            $dateJour = $dto->format('Y-m-d');
            $isToday = $dateJour === $aujourdhui;
            $eventsJour = $eventsParJour[$dateJour] ?? [];
        ?>
            <div class="week-day <?php echo $isToday ? 'today' : ''; ?>">
                <div class="week-day-header">
                    <div class="day-name"><?php echo $jourNomsComplets[$i]; ?></div>
                    <div class="day-num"><?php echo $dto->format('d'); ?></div>
                </div>
                <div class="week-day-content">
                    <?php if (empty($eventsJour)): ?>
                        <p style="color:#aaa;font-size:12px;text-align:center;">Aucun événement</p>
                    <?php else: ?>
                        <?php foreach ($eventsJour as $event):
                            $type = $eventTypes[$event['type']] ?? $eventTypes['autre'];
                        ?>
                            <a href="evenements.php?action=view&id=<?php echo $event['id']; ?>"
                               class="week-event"
                               style="background:<?php echo $type['color']; ?>;">
                                <div class="event-time"><?php echo date('H:i', strtotime($event['date_debut'])); ?></div>
                                <div class="event-title"><?php echo e($event['titre']); ?></div>
                                <?php if ($event['lieu']): ?>
                                    <div class="event-lieu"><?php echo e($event['lieu']); ?></div>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php
            $dto->modify('+1 day');
        endfor;
        ?>
    </div>
    <?php endif; ?>
</div>

<!-- Légende des types -->
<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <h3>Légende</h3>
        <a href="evenements.php?action=new" class="btn btn-primary">+ Nouvel événement</a>
    </div>
    <div class="card-body">
        <div style="display:flex;flex-wrap:wrap;gap:20px;">
            <?php foreach ($eventTypes as $key => $type): ?>
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="width:16px;height:16px;background:<?php echo $type['color']; ?>;border-radius:4px;"></span>
                    <span><?php echo $type['label']; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
