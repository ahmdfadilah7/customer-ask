<script setup>
import { computed, ref, watch } from 'vue'
import { Search } from '@element-plus/icons-vue'

const props = defineProps({
  data: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  searchable: { type: Boolean, default: true },
  searchPlaceholder: { type: String, default: 'Cari data...' },
  pageSize: { type: Number, default: 15 },
  pageSizes: { type: Array, default: () => [10, 15, 25, 50, 100] },
  emptyText: { type: String, default: 'Tidak ada data.' },
  rowKey: { type: String, default: 'id' },
  stripe: { type: Boolean, default: true },
  defaultSort: { type: Object, default: null },
  selectable: { type: Boolean, default: false },
  bare: { type: Boolean, default: false },
})

const emit = defineEmits(['selection-change'])

const search = ref('')
const currentPage = ref(1)
const pageSize = ref(props.pageSize)
const sortProp = ref(props.defaultSort?.prop ?? null)
const sortOrder = ref(props.defaultSort?.order ?? null)

watch(
  () => props.data,
  () => {
    currentPage.value = 1
  },
)

watch(search, () => {
  currentPage.value = 1
})

function rowSearchText(row) {
  return JSON.stringify(row).toLowerCase()
}

function getSortValue(row, prop) {
  const value = prop.split('.').reduce((obj, key) => obj?.[key], row)
  if (value == null) return ''
  if (typeof value === 'number') return value
  if (typeof value === 'boolean') return value ? 1 : 0
  return String(value).toLowerCase()
}

const filteredData = computed(() => {
  const query = search.value.trim().toLowerCase()
  if (!query) return props.data
  return props.data.filter((row) => rowSearchText(row).includes(query))
})

const sortedData = computed(() => {
  const rows = [...filteredData.value]
  if (!sortProp.value || !sortOrder.value) return rows

  rows.sort((a, b) => {
    const av = getSortValue(a, sortProp.value)
    const bv = getSortValue(b, sortProp.value)

    if (av < bv) return sortOrder.value === 'ascending' ? -1 : 1
    if (av > bv) return sortOrder.value === 'ascending' ? 1 : -1
    return 0
  })

  return rows
})

const paginatedData = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value
  return sortedData.value.slice(start, start + pageSize.value)
})

const total = computed(() => filteredData.value.length)

function handleSortChange({ prop, order }) {
  sortProp.value = prop
  sortOrder.value = order
  currentPage.value = 1
}

function handleSelectionChange(rows) {
  emit('selection-change', rows)
}
</script>

<template>
  <div :class="bare ? '' : 'glass-panel overflow-hidden p-4 sm:p-6'">
    <div class="mb-4 flex flex-wrap items-center gap-3">
      <el-input
        v-if="searchable"
        v-model="search"
        :placeholder="searchPlaceholder"
        clearable
        class="app-data-table__search"
      >
        <template #prefix>
          <el-icon><Search /></el-icon>
        </template>
      </el-input>

      <p v-if="!loading" class="text-sm text-slate-500">
        <span class="font-semibold text-slate-800">{{ total }}</span>
        data
        <span v-if="search.trim()" class="text-slate-400">dari {{ data.length }}</span>
      </p>

      <div class="ml-auto flex flex-wrap items-center gap-2">
        <slot name="toolbar" />
      </div>
    </div>

    <el-table
      v-loading="loading"
      :data="paginatedData"
      :row-key="rowKey"
      :stripe="stripe"
      :empty-text="emptyText"
      :default-sort="defaultSort"
      class="app-data-table"
      @sort-change="handleSortChange"
      @selection-change="handleSelectionChange"
    >
      <el-table-column
        v-if="selectable"
        type="selection"
        width="48"
        fixed="left"
        :reserve-selection="true"
      />
      <slot />
    </el-table>

    <div v-if="total > 0" class="mt-4 flex justify-end">
      <el-pagination
        v-model:current-page="currentPage"
        v-model:page-size="pageSize"
        :page-sizes="pageSizes"
        :total="total"
        background
        layout="total, sizes, prev, pager, next, jumper"
      />
    </div>
  </div>
</template>
