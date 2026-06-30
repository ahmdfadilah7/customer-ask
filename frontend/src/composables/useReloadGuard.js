/** Mencegah race condition saat beberapa request load data berjalan bersamaan. */
export function useReloadGuard() {
  let seq = 0

  function nextToken() {
    seq += 1
    return seq
  }

  function isStale(token) {
    return token !== seq
  }

  return { nextToken, isStale }
}
