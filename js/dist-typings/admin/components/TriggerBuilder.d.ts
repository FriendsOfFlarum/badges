import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
import type { TriggerConfig, Condition } from '../../common';
interface TriggerBuilderAttrs {
    config: TriggerConfig | null;
    onchange: (config: TriggerConfig | null) => void;
}
interface MetricOption {
    value: string;
    label: string;
}
export default class TriggerBuilder extends Component<TriggerBuilderAttrs> {
    config: TriggerConfig;
    useDateRange: boolean;
    useTagFilter: boolean;
    tags: any[];
    tagsLoading: boolean;
    oninit(vnode: Mithril.Vnode<TriggerBuilderAttrs>): void;
    loadTags(): Promise<void>;
    view(): Mithril.Children;
    getAvailableMetrics(): MetricOption[];
    hasTagMetric(): boolean;
    addCondition(): void;
    removeCondition(index: number): void;
    updateCondition(index: number, field: keyof Condition, value: string | number): void;
    setLogic(logic: 'AND' | 'OR'): void;
    toggleDateRange(enabled: boolean): void;
    setDateRange(field: 'start' | 'end', value: string): void;
    setTagId(tagId: number | null): void;
    formatDateForInput(isoString?: string): string;
    emitChange(): void;
}
export {};
