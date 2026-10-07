<template>
  <div class="clean-up-tool">
    <k-box
      theme="negative"
      text="This removes saved content fields that are not defined in blueprints. Back up your project and save or discard pending Panel edits first."
    />

    <form class="clean-up-options content-clean-up-options" @submit.prevent="scanContent">
      <k-text-field
        v-model.trim="contentIgnore"
        name="ignore"
        label="Fields to ignore (comma separated)"
        :disabled="contentLoading || contentRunning"
      />
      <k-button
        type="submit"
        icon="refresh"
        variant="filled"
        text="Preview fields"
        :disabled="contentLoading || contentRunning"
      />
    </form>

    <k-box v-if="contentLoading" theme="info" text="Scanning content files..." />

    <template v-if="contentPending !== null && !contentLoading">
      <section class="clean-up-section">
        <k-headline>Fields to remove ({{ contentPending.length }})</k-headline>
        <clean-up-table v-if="contentPending.length" :columns="contentColumns" :rows="contentPending" />
        <k-box v-else theme="positive" text="No undefined content fields found." />
        <clean-up-table v-if="contentErrors.length" :columns="contentErrorColumns" :rows="contentErrors" />
        <k-button
          v-if="contentPending.length"
          class="content-clean-up-button"
          icon="trash"
          theme="red"
          variant="filled"
          :disabled="contentRunning || !contentPreviewIsCurrent"
          :text="`Remove fields from ${contentPending.length} content files`"
          @click="cleanContent"
        />
      </section>
    </template>

    <section v-if="contentResult" class="clean-up-section">
      <k-headline>Cleanup results</k-headline>
      <k-box
        :theme="contentResult.errors.length ? 'warning' : 'positive'"
        :text="`${contentResult.cleaned.length} content files cleaned. ${contentResult.errors.length} errors.`"
      />
      <clean-up-table v-if="contentResult.cleaned.length" :columns="contentColumns" :rows="contentResult.cleaned" />
      <clean-up-table v-if="contentResult.errors.length" :columns="contentErrorColumns" :rows="contentResult.errors" />
      <div class="clean-up-actions">
        <k-button icon="refresh" text="Scan again" :disabled="contentRunning" @click="scanContent" />
      </div>
    </section>
  </div>
</template>

<script>
import CleanUpTable from "./CleanUpTable.vue";

export default {
  components: { CleanUpTable },
  data() {
    return {
      contentIgnore: "uuid, title, slug, template, sort, focus",
      contentLoading: false,
      contentRunning: false,
      contentPending: null,
      contentErrors: [],
      contentResult: null,
      contentPreviewIgnore: null,
      contentColumns: [
        { key: "model", label: "Type" },
        { key: "id", label: "Content file" },
        { key: "language", label: "Language" },
        { key: "fields", label: "Fields" }
      ],
      contentErrorColumns: [
        { key: "model", label: "Type" },
        { key: "id", label: "Content file" },
        { key: "language", label: "Language" },
        { key: "error", label: "Error" }
      ]
    };
  },
  computed: {
    contentPreviewIsCurrent() {
      return this.contentPreviewIgnore === this.contentIgnore;
    }
  },
  methods: {
    async scanContent() {
      if (this.contentRunning) return;
      this.contentLoading = true;
      this.contentResult = null;
      try {
        const result = await this.$api.get("clean-up/content-preview", { ignore: this.contentIgnore });
        this.contentPending = result.pending;
        this.contentErrors = result.errors;
        this.contentPreviewIgnore = this.contentIgnore;
      } catch (error) {
        this.$panel.notification.error(error.message || "Could not scan content files");
      } finally {
        this.contentLoading = false;
      }
    },
    async cleanContent() {
      if (this.contentRunning || !this.contentPreviewIsCurrent) return;
      if (!window.confirm("This removes fields from saved page, file, and user content. Confirm that you have a backup and no unsaved Panel edits.")) return;

      this.contentRunning = true;
      try {
        this.contentResult = await this.$api.post("clean-up/content-clean", { ignore: this.contentIgnore });
        this.contentPending = null;
        this.contentErrors = [];
        if (this.contentResult.errors.length) {
          this.$panel.notification.error(`${this.contentResult.errors.length} content files could not be cleaned`);
        }
      } catch (error) {
        this.$panel.notification.error(error.message || "Content cleanup failed");
      } finally {
        this.contentRunning = false;
      }
    }
  }
};
</script>

<style>
.content-clean-up-options { grid-template-columns: minmax(16rem, 1fr) auto; }
.content-clean-up-button { margin-top: 1rem; }
@media (max-width: 45rem) { .content-clean-up-options { grid-template-columns: 1fr; } }
</style>