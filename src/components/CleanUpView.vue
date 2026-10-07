<template>
  <k-panel-inside class="clean-up-view">
    <k-header>Clean Up</k-header>
    <k-tabs :tab="activeTab" :tabs="tabs" />

    <clean-up-images v-if="active === 'images'" />
    <clean-up-content v-else-if="active === 'content'" />
    <clean-up-orphans v-else />
  </k-panel-inside>
</template>

<script>
import CleanUpContent from "./CleanUpContent.vue";
import CleanUpImages from "./CleanUpImages.vue";
import CleanUpOrphans from "./CleanUpOrphans.vue";

export default {
  components: { CleanUpContent, CleanUpImages, CleanUpOrphans },
  props: { active: { type: String, default: "images" } },
  computed: {
    activeTab() {
      return ["content", "orphans"].includes(this.active) ? this.active : "images";
    },
    tabs() {
      return [
        { name: "images", label: "Images", link: "/clean-up" },
        { name: "content", label: "Content", link: "/clean-up/content" },
        { name: "orphans", label: "Orphaned files", link: "/clean-up/orphans" }
      ];
    }
  }
};
</script>

<style>
.clean-up-view { padding-bottom: 4rem; }
</style>