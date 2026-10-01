<template>
    <card class="flex flex-col items-center justify-center p-4">
        <!-- Title -->
        <h3 class="text-base text-80 font-bold mb-3">
            {{ card.title || 'Daily Quota' }}
        </h3>

        <!-- Progress Bar Track -->
        <div class="w-full bg-40 rounded-full h-3 mb-2 overflow-hidden relative">
            <div 
                class="bg-primary h-full transition-all duration-300"
                :style="{ width: Math.min(card.percentage || 0, 100) + '%' }"
            ></div>
        </div>

        <!-- Percentage Label -->
        <div class="text-sm font-semibold text-80 mb-1">
            {{ card.percentage || 0 }} %
        </div>

        <!-- Subtitle Details -->
        <div class="text-xs text-60 font-medium">
            Quota: {{ card.quota || 0 }} &nbsp;|&nbsp; Sold: {{ card.sold || 0 }}
        </div>
    </card>
</template>

<script>
export default {
    props: [
        'card',
    ],
}
</script>