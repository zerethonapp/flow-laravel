# 📁 flow-laravel — Recommended Repo Structure

## 🎯 Purpose

`flow-laravel` is the first real instrumentation adapter for Flow.

Its job is to:
- hook into Laravel runtime execution
- capture real request flow
- convert Laravel execution into Flow trace format
- feed real traces into `flow-engine` and `flow-cli`

This package should remain focused on **instrumentation only**.

It should **not** own:
- bottleneck scoring
- confidence logic
- insight generation
- CLI formatting

---

## 🧱 Recommended Repository Structure

```text
flow-laravel/
├── src/
│   ├── Providers/
│   │   └── FlowServiceProvider.php
│   ├── Facades/
│   │   └── Flow.php
│   ├── Contracts/
│   │   ├── TraceCollectorInterface.php
│   │   ├── InstrumentationManagerInterface.php
│   │   └── TraceWriterInterface.php
│   ├── Instrumentation/
│   │   ├── InstrumentationManager.php
│   │   ├── TraceCollector.php
│   │   ├── TraceContext.php
│   │   ├── NodeBuilder.php
│   │   ├── EdgeBuilder.php
│   │   ├── TraceWriter.php
│   │   └── Hooks/
│   │       ├── RequestHook.php
│   │       ├── ControllerHook.php
│   │       ├── DatabaseHook.php
│   │       └── ExternalCallHook.php
│   ├── Middleware/
│   │   └── CaptureFlowTrace.php
│   ├── Support/
│   │   ├── GeneratesTraceIds.php
│   │   ├── Clock.php
│   │   └── ArrangesParentChildFlow.php
│   ├── Helpers/
│   │   └── flow.php
│   └── Commands/
│       └── InstallFlowCommand.php
├── config/
│   └── flow.php
├── tests/
│   ├── Feature/
│   │   └── CaptureTraceTest.php
│   └── Unit/
│       ├── TraceCollectorTest.php
│       └── InstrumentationManagerTest.php
├── composer.json
├── README.md
└── .gitignore