<?php

namespace zfrozead0s;

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;
use CortexPE\DiscordWebhookAPI\Message;
use CortexPE\DiscordWebhookAPI\Webhook;
use CortexPE\DiscordWebhookAPI\Embed;
use pocketmine\Server;

class Reports extends PluginBase {

    private Config $reportsConfig;
    private string $webhookUrl;

    public function onEnable(): void {
        @mkdir($this->getDataFolder());
        $this->saveResource("config.yml");
        $this->reportsConfig = new Config($this->getDataFolder() . "reports.yml", Config::YAML);
        $this->webhookUrl = $this->getConfig()->get("webhook_url");

        $this->getLogger()->info(TextFormat::GREEN . "ZF-Reports has been enabled!");
    }

    public function onDisable(): void {
        $this->getLogger()->info(TextFormat::RED . "ZF-Reports has been disabled.");
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($sender instanceof Player) {
            switch (strtolower($command->getName())) {
                case "report":
                    $this->openReportUI($sender);
                    return true;

                case "reports":
                    if (isset($args[0]) && strtolower($args[0]) === "list") {
                        if (!$sender->hasPermission("zfreports.admin")) {
                            $sender->sendMessage(TextFormat::RED . "You don't have permission to use this command.");
                            return false;
                        }
                        $this->openReportsUI($sender);
                        return true;
                    }
                    $sender->sendMessage(TextFormat::RED . "Usage: /reports list");
                    return false;
            }
        } else {
            $sender->sendMessage(TextFormat::RED . "This command can only be used in-game.");
        }
        return false;
    }

    private function openReportUI(Player $player): void {
        $form = new CustomForm(function (Player $player, ?array $data) {
            if ($data === null) {
                return;
            }

            if (empty($data[1]) || empty($data[2])) {
                $player->sendMessage(TextFormat::RED . "Please fill all fields.");
                return;
            }

            $reportedPlayer = $data[1];
            $reason = $data[2];

            $target = Server::getInstance()->getPlayerByPrefix($reportedPlayer);
            if ($target === null) {
                $player->sendMessage(TextFormat::RED . "Player not found.");
                return;
            }

            $this->addReport($player->getName(), $reportedPlayer, $reason);
            $player->sendMessage(TextFormat::GREEN . "Your report has been successfully submitted.");

            $this->sendReportToDiscord($player->getName(), $reportedPlayer, $reason);
        });

        $form->setTitle("Report a Player");
        $form->addLabel("Please fill the details to report a player.");
        $form->addInput("Player to report:", "Enter player's name");
        $form->addInput("Reason:", "Enter the reason");

        $player->sendForm($form);
    }

    private function openReportsUI(Player $player): void {
        $reports = $this->reportsConfig->getAll();

        if (empty($reports)) {
            $player->sendMessage(TextFormat::YELLOW . $this->getConfig()->get("messages")['no_reports']);
            return;
        }

        $form = new SimpleForm(function (Player $player, ?int $data) use ($reports) {
            if ($data === null) {
                return;
            }

            $report = $reports[$data];
            $player->sendMessage(TextFormat::AQUA . "Reported Player: " . TextFormat::WHITE . $report['reported']);
            $player->sendMessage(TextFormat::AQUA . "Reporter: " . TextFormat::WHITE . $report['reporter']);
            $player->sendMessage(TextFormat::AQUA . "Reason: " . TextFormat::WHITE . $report['reason']);
            $player->sendMessage(TextFormat::AQUA . "Time: " . TextFormat::WHITE . $report['time']);
        });

        $form->setTitle("Reports List");
        $form->setContent("Select a report to view details:");

        foreach ($reports as $index => $report) {
            $form->addButton(TextFormat::RED . $report['reported'] . "\n" . TextFormat::GRAY . "Reported by " . $report['reporter']);
        }

        $player->sendForm($form);
    }

    private function addReport(string $reporter, string $reported, string $reason): void {
        $reports = $this->reportsConfig->getAll();
        $reports[] = [
            "reporter" => $reporter,
            "reported" => $reported,
            "reason" => $reason,
            "time" => date("Y-m-d H:i:s")
        ];
        $this->reportsConfig->setAll($reports);
        $this->reportsConfig->save();
    }

    private function sendReportToDiscord(string $reporter, string $reported, string $reason): void {
        if (empty($this->webhookUrl)) {
            $this->getLogger()->warning("Discord webhook URL is not set in the config.yml.");
            return;
        }

        $webhook = new Webhook($this->webhookUrl);
        $msg = new Message();

        $embed = new Embed();
        $embed->setTitle("New Report");
        $embed->setDescription("A new player report has been submitted.");
        $embed->addField("Reported Player", $reported);
        $embed->addField("Reporter", $reporter);
        $embed->addField("Reason", $reason);
        $embed->setColor(0xFF0000);
        $embed->setTimestamp();

        $msg->addEmbed($embed);
        $webhook->send($msg);
    }
}
