<?php

namespace App\Service;

use App\Entity\Commande;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class RecommendationService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function isInactiveSince(User $user, int $days = 30): bool
    {
        $pref = $user->getPreference();
        if (!$pref || !$pref->getLastOrderDate()) return true;
        $diff = (new \DateTime())->diff($pref->getLastOrderDate());
        return $diff->days >= $days;
    }

    public function topItems(User $user, int $limit = 3): array
    {
        $cmds = $this->em->getRepository(Commande::class)->findBy(['user' => $user], ['id'=>'DESC'], 50);
        $count = [];
        foreach ($cmds as $cmd) {
            $snap = $cmd->getCommentaire(); // on va y mettre JSON
            $arr = json_decode($snap ?? '[]', true);
            foreach (($arr['items'] ?? []) as $it) {
                $k = mb_strtolower($it['name'] ?? '');
                if (!$k) continue;
                $count[$k] = ($count[$k] ?? 0) + (int)($it['qty'] ?? 1);
            }
        }
        arsort($count);
        return array_slice(array_keys($count), 0, $limit);
    }
}
