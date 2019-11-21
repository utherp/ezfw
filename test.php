<?php
require_once('uther.ezfw.php');

$uther = player::fetch(1);


function print_player_cards($id) {
    $player = ($id instanceOf player) ? $id : $player = player::fetch($id);
    print "player '{$player->name}' has " . count($player->decks) . " deck(s)\n";

    foreach ($player->decks as $key => $deck) {
        print "  Deck[$key]({$deck->identifier}): {$deck->name}\n";
        foreach ($deck->cards as $card) {
            print "     * [{$card->identifier}] {$card->name}\n";
            foreach ($card->decks as $cdeck) {
                print "       --> in deck({$cdeck->identifier}): {$cdeck->name}\n";
            }
        }
        print "\n\n";
    }
}

$player = player::fetch(1);
print_player_cards($player);
exit;
$deck = $player->decks[1];
$card = $deck->cards[41];
unset($card->decks[$deck->identifier]);
$card->save();

$player = player::fetch(1);
print_player_cards($player);
exit;

$deck = new deck();
$deck->name = 'blah farking blah';
$deck->player_id = $player->identifier;
$deck->save();

$card = new card();
$card->name = 'WEFIJIFIOJ4G';
$card->decks->set($deck->identifier, true);
$card->save();

$card = new card();
$card->name = '2foinq34foiqf';
$card->decks->add($deck->identifier);
$card->decks->add(1);
$card->save();

