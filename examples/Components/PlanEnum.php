<?php

declare(strict_types=1);

namespace App\Components;

enum PlanEnum: string
{
    case Free = 'free';
    case Pro = 'pro';
    case Enterprise = 'enterprise';
}
