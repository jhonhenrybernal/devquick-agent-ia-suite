import { ArrowRight } from 'lucide-react';
import type { ReactNode } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export type AutomationFlowStage = {
    label: string;
    description: string;
    active?: boolean;
};

type Props = {
    badges: string[];
    title: string;
    description: string;
    note?: string;
    actions?: ReactNode;
    stages?: AutomationFlowStage[];
    stageLabel?: string;
    className?: string;
};

export default function AutomationFlowHeader({
    badges,
    title,
    description,
    note,
    actions,
    stages = [],
    stageLabel = 'Flujo preparado',
    className,
}: Props) {
    return (
        <Card
            className={cn(
                'relative overflow-hidden border-border/70 bg-card/80 shadow-sm backdrop-blur',
                className,
            )}
        >
            <div className="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-sky-500 via-cyan-400 to-emerald-400" />
            <div className="absolute -right-16 top-8 size-40 rounded-full bg-primary/10 blur-3xl" />
            <div className="absolute -left-12 bottom-6 size-36 rounded-full bg-amber-500/10 blur-3xl" />

            <div className="space-y-6 p-6 md:p-8">
                <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div className="max-w-2xl space-y-4">
                        <div className="flex flex-wrap gap-2">
                            {badges.map((badge) => (
                                <Badge
                                    key={badge}
                                    variant="secondary"
                                    className="rounded-full px-3 py-1"
                                >
                                    {badge}
                                </Badge>
                            ))}
                        </div>

                        <div className="space-y-2">
                            <Heading title={title} description={description} />
                            {note ? (
                                <p className="max-w-xl text-sm text-muted-foreground">
                                    {note}
                                </p>
                            ) : null}
                        </div>
                    </div>

                    {actions ? <div className="flex flex-wrap gap-3">{actions}</div> : null}
                </div>

                {stages.length > 0 ? (
                    <div className="space-y-3 rounded-3xl border border-border/60 bg-background/70 p-4">
                        <div className="flex items-center gap-2 text-xs uppercase tracking-[0.22em] text-muted-foreground">
                            <span>{stageLabel}</span>
                            <ArrowRight className="h-3.5 w-3.5" />
                        </div>

                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                            {stages.map((stage, index) => (
                                <div
                                    key={stage.label}
                                    className={cn(
                                        'rounded-2xl border p-4 transition-all',
                                        stage.active
                                            ? 'border-primary/40 bg-primary/5 shadow-sm'
                                            : 'border-border/60 bg-card/70',
                                    )}
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                Paso {index + 1}
                                            </p>
                                            <p className="mt-2 text-base font-semibold">
                                                {stage.label}
                                            </p>
                                        </div>
                                        <Badge
                                            variant={stage.active ? 'default' : 'secondary'}
                                            className="rounded-full px-3 py-1"
                                        >
                                            {stage.active ? 'Listo' : 'Pendiente'}
                                        </Badge>
                                    </div>
                                    <p className="mt-3 text-sm text-muted-foreground">
                                        {stage.description}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                ) : null}
            </div>
        </Card>
    );
}
