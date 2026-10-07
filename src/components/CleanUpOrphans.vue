<template>
  <div class="clean-up-tool">
    <k-box
      theme="negative"
      text="A content scan cannot detect references in templates, code, JavaScript, or external systems. Back up your project and review the list before deleting."
    />

    <div class="clean-up-actions">
      <k-button icon="refresh" text="Scan files" :disabled="loading || running" @click="scan" />
    </div>

    <k-box v-if="loading" theme="info" text="Scanning content references..." />

    <template v-if="pending !== null && !loading">
      <section class="clean-up-section">
        <k-headline>Possible orphans ({{ pending.length }})</k-headline>
        <clean-up-table v-if="pending.length" :columns="fileColumns" :rows="pending" />
        <k-box v-else theme="positive" text="No unreferenced files found." />
        <clean-up-table v-if="errors.length" :columns="errorColumns" :rows="errors" />
        <k-button
          v-if="pending.length"
          class="orphan-delete-button"
          icon="trash"
          theme="red"
          variant="filled"
          :disabled="running || errors.length > 0"
          :text="`Delete ${pending.length} possible orphaned files`"
          @click="deleteFiles"
        />
      </section>
    </template>

    <section v-if="result" class="clean-up-section">
      <k-headline>Deletion results</k-headline>
      <k-box
        :theme="result.errors.length || result.skipped.length ? 'warning' : 'positive'"
        :text="`${result.deleted.length} files deleted. ${result.skipped.length} skipped. ${result.errors.length} errors.`"
      />
      <clean-up-table v-if="result.deleted.length" :columns="fileColumns" :rows="result.deleted" />
      <clean-up-table v-if="result.skipped.length" :columns="skippedColumns" :rows="result.skipped" />
      <clean-up-table v-if="result.errors.length" :columns="errorColumns" :rows="result.errors" />
    </section>
  </div>
</template>

<script>
import CleanUpTable from "./CleanUpTable.vue";

export default {
  components: { CleanUpTable },
  data() {
    return {
      loading: false,
      running: false,
      pending: null,
      errors: [],
      result: null,
      fileColumns: [
        { key: "filename", label: "File" },
        { key: "location", label: "Location" },
        { key: "id", label: "Kirby ID" }
      ],
      skippedColumns: [{ key: "id", label: "File" }],
      errorColumns: [
        { key: "id", label: "Content model" },
        { key: "language", label: "Language" },
        { key: "error", label: "Error" }
      ]
    };
  },
  mounted() {
    this.scan();
  },
  methods: {
    async scan() {
      if (this.running) return;
      this.loading = true;
      this.result = null;
      try {
        const result = await this.$api.get("clean-up/orphaned-files-preview");
        this.pending = result.pending;
        this.errors = result.errors;
      } catch (error) {
        this.$panel.notification.error(error.message || "Could not scan file references");
      } finally {
        this.loading = false;
      }
    },
    async deleteFiles() {
      if (this.running || this.errors.length > 0 || !this.pending?.length) return;
      const count = this.pending.length;
      if (!window.confirm(`Delete ${count} files that appear unreferenced? This cannot be undone. Confirm you have reviewed the list and made a backup.`)) return;

      this.running = true;
      try {
        this.result = await this.$api.post("clean-up/orphaned-files-delete", {
          ids: this.pending.map((file) => file.id)
        });
        this.pending = null;
        this.errors = [];
        if (this.result.errors.length || this.result.skipped.length) {
          this.$panel.notification.error("Some files were skipped or could not be deleted");
        }
      } catch (error) {
        this.$panel.notification.error(error.message || "Could not delete files");
      } finally {
        this.running = false;
      }
    }
  }
};
</script>

<style>
.orphan-delete-button { margin-top: 1rem; }
</style>