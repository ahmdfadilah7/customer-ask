<script setup>
import { computed, useAttrs } from 'vue'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  prop: { type: String, default: undefined },
  label: { type: String, default: '' },
  width: { type: [String, Number], default: undefined },
  minWidth: { type: [String, Number], default: undefined },
  align: { type: String, default: undefined },
  fixed: { type: [Boolean, String], default: undefined },
  sortable: { type: Boolean, default: true },
  showOverflowTooltip: { type: Boolean, default: false },
})

const attrs = useAttrs()

const sortableMode = computed(() => (props.sortable ? 'custom' : false))
</script>

<template>
  <el-table-column
    v-bind="attrs"
    :prop="prop"
    :label="label"
    :width="width"
    :min-width="minWidth"
    :align="align"
    :fixed="fixed"
    :sortable="sortableMode"
    :show-overflow-tooltip="showOverflowTooltip"
  >
    <template v-if="$slots.default" #default="scope">
      <slot v-bind="scope" />
    </template>
    <template v-if="$slots.header" #header="scope">
      <slot name="header" v-bind="scope" />
    </template>
  </el-table-column>
</template>
