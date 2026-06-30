import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

export function useBranchScope() {
  const auth = useAuthStore()

  const isBranchScoped = computed(() => {
    if (!auth.user) return false
    return !auth.user.has_full_branch_access
  })

  const userBranches = computed(() => auth.user?.branches ?? [])

  function resolveBranchOptions(apiBranches = []) {
    if (!isBranchScoped.value) {
      return apiBranches
    }

    if (userBranches.value.length > 0) {
      return userBranches.value
    }

    const allowed = new Set(userBranches.value.map((b) => b.id))
    return apiBranches.filter((b) => allowed.has(b.id))
  }

  function shouldShowBranchSelector(options = []) {
    if (!isBranchScoped.value) {
      return true
    }

    return options.length > 1
  }

  function formatBranchLabel(branch) {
    if (!branch) return ''
    return branch.code ? `${branch.name} (${branch.code})` : branch.name
  }

  function findBranchLabel(options, branchId) {
    const branch = options.find((b) => String(b.id) === String(branchId))
    return formatBranchLabel(branch)
  }

  /** Auto-set cabang untuk user yang dibatasi per cabang. */
  function initBranchValue(branchRef, options = []) {
    if (!options.length) {
      branchRef.value = ''
      return
    }

    if (isBranchScoped.value) {
      branchRef.value = options[0].id
      return
    }

    if (options.length === 1 && !branchRef.value) {
      branchRef.value = options[0].id
    }
  }

  return {
    isBranchScoped,
    userBranches,
    resolveBranchOptions,
    shouldShowBranchSelector,
    formatBranchLabel,
    findBranchLabel,
    initBranchValue,
  }
}
